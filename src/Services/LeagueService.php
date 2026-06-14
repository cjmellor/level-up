<?php

declare(strict_types=1);

namespace LevelUp\Experience\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LevelUp\Experience\Enums\DivisionDirection;
use LevelUp\Experience\Enums\Period;
use LevelUp\Experience\Events\UserDivisionChanged;
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

        $division = $this->heldDivisionFor(user: $user) ?? $this->bottomDivision();

        $this->openCohort(division: $division, range: $range)->users()->attach($user->getKey());
    }

    public function rollover(): void
    {
        if (! $this->configured()) {
            return;
        }

        $closed = $this->cohortQuery()
            ->with(relations: 'division')
            ->whereNull(columns: 'rolled_over_at')
            ->where(column: 'period_end', operator: '<=', value: now())
            ->get();

        if ($closed->isEmpty()) {
            return;
        }

        $ladder = $this->syncDivisions();

        foreach ($closed as $cohort) {
            $this->rolloverCohort(cohort: $cohort, ladder: $ladder);

            $cohort->update(attributes: ['rolled_over_at' => now()]);
        }
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

        return $this->heldDivisionFor(user: $user);
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

    private function heldDivisionFor(Model $user): ?Division
    {
        $cohort = $this->latestCohortFor(user: $user);

        if (! $cohort instanceof Cohort) {
            return null;
        }

        return $this->nextDivisionFor(cohort: $cohort, user: $user) ?? $cohort->division;
    }

    private function nextDivisionFor(Cohort $cohort, Model $user): ?Division
    {
        $members = $cohort->users();

        $nextDivisionId = $members->newPivotStatement()
            ->where(column: $members->getForeignPivotKeyName(), operator: '=', value: $cohort->getKey())
            ->where(column: $members->getRelatedPivotKeyName(), operator: '=', value: $user->getKey())
            ->value(column: 'next_division_id');

        if ($nextDivisionId === null) {
            return null;
        }

        /** @var class-string<Division> $divisionClass */
        $divisionClass = config(key: 'level-up.models.division');

        return $divisionClass::query()->find(id: $nextDivisionId);
    }

    /**
     * @param  Collection<int, Division>  $ladder
     */
    private function rolloverCohort(Cohort $cohort, Collection $ladder): void
    {
        $standings = $this->finalStandings(cohort: $cohort)->values();
        $division = $cohort->division;

        $promoted = $this->promoteTop(cohort: $cohort, standings: $standings, division: $division, ladder: $ladder);

        $this->relegateBottom(cohort: $cohort, standings: $standings, division: $division, ladder: $ladder, promoted: $promoted);
    }

    /**
     * @param  Collection<int, LeaderboardEntry>  $standings
     * @param  Collection<int, Division>  $ladder
     * @return Collection<int, LeaderboardEntry>
     */
    private function promoteTop(Cohort $cohort, Collection $standings, Division $division, Collection $ladder): Collection
    {
        $above = $this->rungAt(ladder: $ladder, position: $division->position + 1);

        if (! $above instanceof Division) {
            return new Collection();
        }

        $promoted = $standings->slice(offset: 0, length: $this->movementCount(division: $division, key: 'promote'));

        foreach ($promoted as $entry) {
            $this->moveUser(cohort: $cohort, user: $entry->user, from: $division, to: $above, direction: DivisionDirection::Promoted);
        }

        return $promoted;
    }

    /**
     * @param  Collection<int, LeaderboardEntry>  $standings
     * @param  Collection<int, Division>  $ladder
     * @param  Collection<int, LeaderboardEntry>  $promoted
     */
    private function relegateBottom(Cohort $cohort, Collection $standings, Division $division, Collection $ladder, Collection $promoted): void
    {
        $below = $this->rungAt(ladder: $ladder, position: $division->position - 1);

        if (! $below instanceof Division) {
            return;
        }

        $promotedKeys = $promoted->map(callback: fn (LeaderboardEntry $entry): mixed => $entry->user->getKey())->all();

        $relegated = $standings
            ->slice(offset: max(0, $standings->count() - $this->movementCount(division: $division, key: 'relegate')))
            ->reject(callback: fn (LeaderboardEntry $entry): bool => in_array(needle: $entry->user->getKey(), haystack: $promotedKeys, strict: true));

        foreach ($relegated as $entry) {
            $this->moveUser(cohort: $cohort, user: $entry->user, from: $division, to: $below, direction: DivisionDirection::Relegated);
        }
    }

    private function moveUser(Cohort $cohort, Model $user, Division $from, Division $to, DivisionDirection $direction): void
    {
        $cohort->users()->updateExistingPivot(id: $user->getKey(), attributes: ['next_division_id' => $to->getKey()]);

        event(new UserDivisionChanged(
            user: $user,
            board: config()->string(key: 'level-up.leaderboard.league.board'),
            previousDivision: $from,
            newDivision: $to,
            direction: $direction,
        ));
    }

    /**
     * @param  Collection<int, Division>  $ladder
     */
    private function rungAt(Collection $ladder, int $position): ?Division
    {
        return $ladder->first(callback: fn (Division $rung): bool => $rung->position === $position);
    }

    private function movementCount(Division $division, string $key): int
    {
        $declared = config()->array(key: 'level-up.leaderboard.league.divisions');
        $movement = $declared[$division->name] ?? null;
        $count = is_array($movement) ? ($movement[$key] ?? null) : null;

        return is_int($count) && $count > 0 ? $count : 0;
    }

    /**
     * @return Collection<int, LeaderboardEntry>
     */
    private function finalStandings(Cohort $cohort): Collection
    {
        $members = $cohort->users();
        $memberKeys = $members->pluck(column: $members->getRelated()->getQualifiedKeyName())->all();

        /** @var Collection<int, LeaderboardEntry> $entries */
        $entries = resolve(name: LeaderboardService::class)
            ->board(name: config()->string(key: 'level-up.leaderboard.league.board'))
            ->since(start: $cohort->period_start, until: $cohort->period_end)
            ->restrictTo(constraint: fn (Builder $query): Builder => $query->whereKey(id: $memberKeys))
            ->generate();

        return $entries;
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
