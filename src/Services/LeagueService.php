<?php

declare(strict_types=1);

namespace LevelUp\Experience\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LevelUp\Experience\Enums\Period;
use LevelUp\Experience\Exceptions\BoardNotFoundException;
use LevelUp\Experience\Exceptions\LeagueBoardNotPeriodicException;
use LevelUp\Experience\Exceptions\LeagueDivisionsNotDeclaredException;
use LevelUp\Experience\Models\Cohort;
use LevelUp\Experience\Models\Division;
use LevelUp\Experience\Support\LeaderboardEntry;
use LevelUp\Experience\Support\PeriodRange;

class LeagueService
{
    public function configured(): bool
    {
        $board = config(key: 'level-up.leaderboard.league.board');

        return is_string($board) && $board !== '';
    }

    public function enroll(Model $user): void
    {
        if (! $this->configured()) {
            return;
        }

        $range = $this->currentRange();

        if ($this->cohortForRange(user: $user, range: $range) instanceof Cohort) {
            return;
        }

        $latestCohort = $this->latestCohortFor(user: $user);
        $division = $latestCohort instanceof Cohort ? $latestCohort->division : $this->bottomDivision();

        $this->openCohort(division: $division, range: $range)->users()->attach($user->getKey());
    }

    public function cohortFor(Model $user): ?Cohort
    {
        if (! $this->configured()) {
            return null;
        }

        return $this->cohortForRange(user: $user, range: $this->currentRange());
    }

    public function divisionFor(Model $user): ?Division
    {
        if (! $this->configured()) {
            return null;
        }

        return $this->latestCohortFor(user: $user)?->division;
    }

    /**
     * @return Collection<int, LeaderboardEntry>
     */
    public function standingsFor(Model $user): Collection
    {
        $cohort = $this->cohortFor(user: $user);

        if (! $cohort instanceof Cohort) {
            return new Collection();
        }

        $memberKeys = $cohort->users()->pluck(column: $user->getQualifiedKeyName())->all();

        /** @var Collection<int, LeaderboardEntry> $entries */
        $entries = resolve(name: LeaderboardService::class)
            ->board(name: config()->string(key: 'level-up.leaderboard.league.board'))
            ->restrictTo(constraint: fn (Builder $query): Builder => $query->whereKey(id: $memberKeys))
            ->generate();

        return $entries;
    }

    private function currentRange(): PeriodRange
    {
        return $this->boardPeriod()->range();
    }

    private function boardPeriod(): Period
    {
        $board = config()->string(key: 'level-up.leaderboard.league.board');
        $boards = config()->array(key: 'level-up.leaderboard.boards');
        $declaration = $boards[$board] ?? null;

        throw_unless(is_array($declaration), exception: BoardNotFoundException::forLeague($board));

        $period = $declaration['period'] ?? null;

        throw_if($period === null, exception: LeagueBoardNotPeriodicException::forBoard($board));

        return $period instanceof Period ? $period : Period::from(value: is_string($period) ? $period : '');
    }

    private function cohortForRange(Model $user, PeriodRange $range): ?Cohort
    {
        return $this->cohortQuery()
            ->where(column: 'period_start', operator: '=', value: $range->start)
            ->whereHas(relation: 'users', callback: fn (Builder $query): Builder => $query->whereKey($user->getKey()))
            ->first();
    }

    private function latestCohortFor(Model $user): ?Cohort
    {
        return $this->cohortQuery()
            ->whereHas(relation: 'users', callback: fn (Builder $query): Builder => $query->whereKey($user->getKey()))
            ->orderByDesc(column: 'period_start')
            ->first();
    }

    private function bottomDivision(): Division
    {
        return $this->syncDivisions()->firstOrFail();
    }

    /**
     * @return Collection<int, Division>
     */
    private function syncDivisions(): Collection
    {
        $declared = config()->array(key: 'level-up.leaderboard.league.divisions');

        throw_if($declared === [], exception: LeagueDivisionsNotDeclaredException::make());

        /** @var class-string<Division> $divisionClass */
        $divisionClass = config(key: 'level-up.models.division');

        $divisions = new Collection();
        $position = 1;

        foreach (array_keys($declared) as $name) {
            $divisions->push($divisionClass::query()->firstOrCreate(
                attributes: ['name' => (string) $name],
                values: ['position' => $position],
            ));

            $position++;
        }

        return $divisions;
    }

    private function openCohort(Division $division, PeriodRange $range): Cohort
    {
        $cohortSize = config()->integer(key: 'level-up.leaderboard.league.cohort_size', default: 30);

        $open = $division->cohorts()
            ->withCount(relations: 'users')
            ->where(column: 'period_start', operator: '=', value: $range->start)
            ->get()
            ->first(callback: fn (Cohort $cohort): bool => $cohort->users_count < $cohortSize);

        return $open ?? $division->cohorts()->create(attributes: [
            'period_start' => $range->start,
            'period_end' => $range->end,
        ]);
    }

    /**
     * @return Builder<Cohort>
     */
    private function cohortQuery(): Builder
    {
        /** @var class-string<Cohort> $cohortClass */
        $cohortClass = config(key: 'level-up.models.cohort');

        return $cohortClass::query();
    }
}
