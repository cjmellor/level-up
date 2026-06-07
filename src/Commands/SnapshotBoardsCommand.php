<?php

declare(strict_types=1);

namespace LevelUp\Experience\Commands;

use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LevelUp\Experience\Events\LeaderboardRankChanged;
use LevelUp\Experience\Events\UserEnteredTrackedDepth;
use LevelUp\Experience\Events\UserLeftTrackedDepth;
use LevelUp\Experience\Models\LeaderboardSnapshot;
use LevelUp\Experience\Services\LeaderboardService;
use LevelUp\Experience\Support\LeaderboardEntry;

class SnapshotBoardsCommand extends Command
{
    protected $signature = 'level-up:snapshot-boards';

    protected $description = 'Snapshot the top entries of every declared leaderboard Board, dispatch rank events, and prune old runs.';

    public function handle(LeaderboardService $leaderboard): int
    {
        $boards = config()->array(key: 'level-up.leaderboard.boards');
        $runAt = now();

        foreach (array_keys($boards) as $name) {
            $this->snapshotBoard(leaderboard: $leaderboard, name: (string) $name, declaration: $boards[$name], runAt: $runAt);
        }

        $this->pruneStaleRuns(runAt: $runAt);

        return self::SUCCESS;
    }

    private function pruneStaleRuns(CarbonInterface $runAt): void
    {
        $retentionDays = config()->integer(key: 'level-up.leaderboard.snapshots.retention_days', default: 30);

        $snapshotModel = config(key: 'level-up.models.leaderboard_snapshot');

        $snapshotModel::query()
            ->where(column: 'run_at', operator: '<', value: $runAt->copy()->subDays($retentionDays))
            ->delete();
    }

    private function snapshotBoard(LeaderboardService $leaderboard, string $name, mixed $declaration, CarbonInterface $runAt): void
    {
        $trackTop = is_array($declaration) && is_int($declaration['track_top'] ?? null) ? $declaration['track_top'] : 100;

        /** @var Collection<int, LeaderboardEntry> $entries */
        $entries = $leaderboard->board(name: $name)->generate(limit: $trackTop);

        $snapshotModel = config(key: 'level-up.models.leaderboard_snapshot');
        $userForeignKey = config()->string(key: 'level-up.user.foreign_key');

        $previousRunAt = $snapshotModel::query()
            ->where(column: 'board', operator: '=', value: $name)
            ->where(column: 'run_at', operator: '<', value: $runAt)
            ->max(column: 'run_at');

        $snapshotModel::query()
            ->where(column: 'board', operator: '=', value: $name)
            ->where(column: 'run_at', operator: '=', value: $runAt)
            ->delete();

        foreach ($entries as $entry) {
            $snapshotModel::query()->create(attributes: [
                'board' => $name,
                $userForeignKey => $entry->user->getKey(),
                'rank' => $entry->rank,
                'score' => $entry->score,
                'run_at' => $runAt,
            ]);
        }

        if ($previousRunAt === null) {
            return;
        }

        /** @var Collection<int|string, LeaderboardSnapshot> $previousSnapshots */
        $previousSnapshots = $snapshotModel::query()
            ->with(relations: ['user'])
            ->where(column: 'board', operator: '=', value: $name)
            ->where(column: 'run_at', operator: '=', value: $previousRunAt)
            ->get()
            ->toBase()
            ->keyBy(keyBy: $userForeignKey);

        $this->dispatchRankEvents(name: $name, entries: $entries, previousSnapshots: $previousSnapshots);
    }

    /**
     * @param  Collection<int, LeaderboardEntry>  $entries
     * @param  Collection<int|string, LeaderboardSnapshot>  $previousSnapshots
     */
    private function dispatchRankEvents(string $name, Collection $entries, Collection $previousSnapshots): void
    {
        $currentKeys = [];

        foreach ($entries as $entry) {
            $key = $entry->user->getKey();
            $currentKeys[] = $key;

            $previous = $previousSnapshots->get(key: $key);

            if (! $previous instanceof Model) {
                event(new UserEnteredTrackedDepth(user: $entry->user, board: $name, rank: $entry->rank));

                continue;
            }

            $from = (int) $previous->getAttribute(key: 'rank');

            if ($from !== $entry->rank) {
                event(new LeaderboardRankChanged(user: $entry->user, board: $name, from: $from, to: $entry->rank));
            }
        }

        foreach ($previousSnapshots->except(keys: $currentKeys) as $departed) {
            event(new UserLeftTrackedDepth(
                user: $departed->getRelation(relation: 'user'),
                board: $name,
                previousRank: (int) $departed->getAttribute(key: 'rank'),
            ));
        }
    }
}
