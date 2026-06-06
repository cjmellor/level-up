<?php

declare(strict_types=1);

namespace LevelUp\Experience\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LevelUp\Experience\Contracts\RankingMetric;
use LevelUp\Experience\Exceptions\MetricDisabledException;
use LevelUp\Experience\Exceptions\MetricNotFoundException;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Support\LeaderboardEntry;

class LeaderboardService
{
    private readonly string $userModel;

    private ?Tier $tier = null;

    private ?RankingMetric $metric = null;

    public function __construct()
    {
        $this->userModel = config(key: 'level-up.user.model');
    }

    public function by(string|RankingMetric $metric): static
    {
        $this->metric = $this->resolveMetric($metric);

        return $this;
    }

    public function forTier(string|Tier $tier): static
    {
        if (is_string($tier)) {
            $tierClass = config(key: 'level-up.models.tier');
            $tier = $tierClass::where(column: 'name', operator: '=', value: $tier)->firstOrFail();
        }

        $this->tier = $tier;

        return $this;
    }

    public function generate(bool $paginate = false, ?int $limit = null): Collection|LengthAwarePaginator
    {
        [$metric, $tier] = $this->consumeContext();

        $query = $this->rankedQuery(metric: $metric, tier: $tier)->take(value: $limit);

        return $paginate
            ? $query->paginate()->through(callback: fn (Model $user): LeaderboardEntry => $this->toEntry($user))
            : $query->get()->map(callback: fn (Model $user): LeaderboardEntry => $this->toEntry($user));
    }

    public function rankOf(Model $user): ?int
    {
        [$metric, $tier] = $this->consumeContext();

        $rank = DB::query()
            ->fromSub(query: $this->rankedQuery(metric: $metric, tier: $tier), as: 'ranked')
            ->where(column: $user->getKeyName(), operator: '=', value: $user->getKey())
            ->value(column: 'rank');

        return $rank === null ? null : (int) $rank;
    }

    public function around(Model $user, int $range): Collection
    {
        [$metric, $tier] = $this->consumeContext();

        $ranked = $this->rankedQuery(metric: $metric, tier: $tier);
        $grammar = $ranked->getQuery()->getGrammar();

        $positioned = (clone $ranked)->selectRaw(expression: sprintf(
            'ROW_NUMBER() OVER (ORDER BY %s DESC, %s ASC) AS %s',
            $grammar->wrap(value: 'score'),
            $grammar->wrap(value: $user->getKeyName()),
            $grammar->wrap(value: 'position'),
        ));

        $position = DB::query()
            ->fromSub(query: $positioned, as: 'positioned')
            ->where(column: $user->getKeyName(), operator: '=', value: $user->getKey())
            ->value(column: 'position');

        if ($position === null) {
            return new Collection();
        }

        $offset = max(0, (int) $position - 1 - $range);

        return $ranked
            ->skip(value: $offset)
            ->take(value: (int) $position + $range - $offset)
            ->get()
            ->map(callback: fn (Model $user): LeaderboardEntry => $this->toEntry($user))
            ->toBase();
    }

    /**
     * @return array{0: RankingMetric, 1: ?Tier}
     */
    private function consumeContext(): array
    {
        [$tier, $this->tier] = [$this->tier, null];
        [$metric, $this->metric] = [$this->metric ?? $this->defaultMetric(), null];

        throw_unless($metric->enabled(), exception: MetricDisabledException::forMetric($metric));

        return [$metric, $tier];
    }

    private function rankedQuery(RankingMetric $metric, ?Tier $tier): Builder
    {
        $scored = $this->userModel::query()
            ->select(columns: config(key: 'level-up.user.users_table').'.*')
            ->selectSub(query: $metric->scoreExpression(), as: 'score')
            ->tap(callback: fn (Builder $query): Builder => $metric->constrain($query))
            ->when($tier instanceof Tier, fn (Builder $query): Builder => $query->whereHas(
                'experience',
                fn (Builder $query): Builder => $query->where(column: 'tier_id', operator: '=', value: $tier->id),
            ));

        $grammar = $scored->getQuery()->getGrammar();
        $keyName = $scored->getModel()->getKeyName();

        return $this->userModel::query()
            ->fromSub(query: $scored, as: 'board')
            ->select(columns: 'board.*')
            ->selectRaw(expression: sprintf(
                'RANK() OVER (ORDER BY %s DESC) AS %s',
                $grammar->wrap(value: 'score'),
                $grammar->wrap(value: 'rank'),
            ))
            ->with(relations: ['experience'])
            ->orderByDesc(column: 'score')
            ->orderBy(column: $keyName);
    }

    private function toEntry(Model $user): LeaderboardEntry
    {
        $score = $user->getAttribute(key: 'score') + 0;
        $rank = (int) $user->getAttribute(key: 'rank');

        unset($user->score, $user->rank);

        return new LeaderboardEntry(user: $user, score: $score, rank: $rank);
    }

    private function defaultMetric(): RankingMetric
    {
        return $this->resolveMetric(config(key: 'level-up.leaderboard.default_metric'));
    }

    private function resolveMetric(string|RankingMetric $metric): RankingMetric
    {
        if ($metric instanceof RankingMetric) {
            return $metric;
        }

        $metricClass = config(
            key: "level-up.leaderboard.metrics.{$metric}",
            default: is_subclass_of($metric, RankingMetric::class) ? $metric : null,
        );

        throw_unless($metricClass, exception: MetricNotFoundException::forKey($metric));

        return resolve(name: $metricClass);
    }
}
