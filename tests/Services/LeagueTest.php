<?php

declare(strict_types=1);

uses()->group('league');

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\UserTierUpdated;
use LevelUp\Experience\Exceptions\BoardNotFoundException;
use LevelUp\Experience\Exceptions\LeagueBoardNotPeriodicException;
use LevelUp\Experience\Exceptions\LeagueDivisionsNotDeclaredException;
use LevelUp\Experience\Models\Cohort;
use LevelUp\Experience\Models\Division;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

function configureLeague(int $cohortSize = 30): void
{
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['metric' => 'xp', 'period' => 'week'],
    ]]);

    config(['level-up.leaderboard.league' => [
        'board' => 'weekly-xp',
        'cohort_size' => $cohortSize,
        'divisions' => [
            'Bronze' => ['promote' => 10, 'relegate' => 0],
            'Silver' => ['promote' => 7, 'relegate' => 5],
            'Gold' => ['promote' => 0, 'relegate' => 5],
        ],
    ]]);
}

it(description: 'enrolls a user into a cohort in the bottom division on their first earn', closure: function (): void {
    configureLeague();

    $user = User::newFactory()->create();
    $user->addPoints(amount: 50);

    $cohort = $user->currentCohort();

    expect($cohort)->toBeInstanceOf(class: Cohort::class)
        ->and($user->currentDivision()?->name)->toBe(expected: 'Bronze')
        ->and($cohort->users()->count())->toBe(expected: 1);
});

it(description: 'does not re-enroll a user on subsequent earns in the same period', closure: function (): void {
    configureLeague();

    $user = User::newFactory()->create();
    $user->addPoints(amount: 50);
    $user->addPoints(amount: 25);

    expect(Cohort::query()->count())->toBe(expected: 1)
        ->and(DB::table(table: 'cohort_user')->count())->toBe(expected: 1);
});

it(description: 'fills the open cohort in arrival order until it reaches cohort_size', closure: function (): void {
    configureLeague(cohortSize: 2);

    $first = tap(User::newFactory()->create())->addPoints(amount: 10);
    $second = tap(User::newFactory()->create())->addPoints(amount: 20);

    expect(Cohort::query()->count())->toBe(expected: 1)
        ->and($first->currentCohort()?->id)->toEqual($second->currentCohort()?->id);
});

it(description: 'opens a new cohort in the same division when the open cohort is full', closure: function (): void {
    configureLeague(cohortSize: 2);

    $first = tap(User::newFactory()->create())->addPoints(amount: 10);
    tap(User::newFactory()->create())->addPoints(amount: 20);
    $overflow = tap(User::newFactory()->create())->addPoints(amount: 30);

    expect(Cohort::query()->count())->toBe(expected: 2)
        ->and($overflow->currentCohort()?->id)->not->toEqual($first->currentCohort()?->id)
        ->and($overflow->currentDivision()?->name)->toBe(expected: 'Bronze')
        ->and($overflow->currentCohort()?->users()->count())->toBe(expected: 1);
});

it(description: 'never cohorts a ghost — a user with no qualifying activity in the period', closure: function (): void {
    configureLeague();

    tap(User::newFactory()->create())->addPoints(amount: 50);
    $ghost = User::newFactory()->create();

    expect($ghost->currentCohort())->toBeNull()
        ->and($ghost->currentDivision())->toBeNull()
        ->and(DB::table(table: 'cohort_user')->count())->toBe(expected: 1);
});

it(description: "carries a ghost's division across periods without cohorting them", closure: function (): void {
    configureLeague();

    $this->travelTo(Date::parse(time: '2026-06-03 12:00:00'));
    $user = tap(User::newFactory()->create())->addPoints(amount: 50);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));

    expect($user->currentCohort())->toBeNull()
        ->and($user->currentDivision()?->name)->toBe(expected: 'Bronze');
});

it(description: 'enrolls a returning user into their held division, not the bottom one', closure: function (): void {
    configureLeague();

    Division::query()->create(attributes: ['name' => 'Bronze', 'position' => 1]);
    $silver = Division::query()->create(attributes: ['name' => 'Silver', 'position' => 2]);

    $this->travelTo(Date::parse(time: '2026-06-03 12:00:00'));
    $user = User::newFactory()->create();

    $lastWeek = Cohort::query()->create(attributes: [
        'division_id' => $silver->id,
        'period_start' => Date::parse(time: '2026-06-01 00:00:00'),
        'period_end' => Date::parse(time: '2026-06-08 00:00:00'),
    ]);
    $lastWeek->users()->attach($user->id);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    $user->addPoints(amount: 50);

    expect($user->currentCohort()?->division->name)->toBe(expected: 'Silver');
});

it(description: 'ranks cohort standings within the cohort only', closure: function (): void {
    configureLeague(cohortSize: 2);

    $runnerUp = tap(User::newFactory()->create())->addPoints(amount: 50);
    $leader = tap(User::newFactory()->create())->addPoints(amount: 80);
    tap(User::newFactory()->create())->addPoints(amount: 500);

    $standings = $runnerUp->cohortStandings();

    expect($standings)->toHaveCount(count: 2)
        ->and($standings->first()->user->id)->toEqual($leader->id)
        ->and($standings->first()->rank)->toBe(expected: 1)
        ->and($standings->first()->score)->toBe(expected: 80)
        ->and($standings->last()->user->id)->toEqual($runnerUp->id)
        ->and($standings->last()->rank)->toBe(expected: 2)
        ->and($standings->last()->score)->toBe(expected: 50);
});

it(description: 'seeds the division ladder from config, bottom to top', closure: function (): void {
    configureLeague();

    tap(User::newFactory()->create())->addPoints(amount: 50);

    $ladder = Division::query()->orderBy(column: 'position')->get();

    expect($ladder->pluck('name')->all())->toBe(expected: ['Bronze', 'Silver', 'Gold'])
        ->and($ladder->pluck('position')->all())->toBe(expected: [1, 2, 3]);
});

it(description: 'stays dormant when no league is configured', closure: function (): void {
    $user = User::newFactory()->create();
    $user->addPoints(amount: 50);

    expect(Cohort::query()->count())->toBe(expected: 0)
        ->and(Division::query()->count())->toBe(expected: 0)
        ->and($user->currentCohort())->toBeNull()
        ->and($user->currentDivision())->toBeNull()
        ->and($user->cohortStandings())->toBeEmpty();
});

it(description: 'leaves tier behavior untouched while the user competes in a division', closure: function (): void {
    configureLeague();

    Event::fake(eventsToFake: [UserTierUpdated::class]);

    Tier::add(
        ['name' => 'Wood', 'experience' => 0],
        ['name' => 'Stone', 'experience' => 500],
    );

    $user = User::newFactory()->create();
    $user->addPoints(amount: 550);

    expect($user->getTier()?->name)->toBe(expected: 'Stone')
        ->and($user->experience->tier_id)->toEqual(Tier::query()->where(column: 'name', operator: '=', value: 'Stone')->first()?->id)
        ->and($user->currentDivision()?->name)->toBe(expected: 'Bronze');

    Event::assertDispatched(event: UserTierUpdated::class, callback: fn (UserTierUpdated $event): bool => $event->newTier?->name === 'Stone');
});

it(description: 'throws on first earn when the league references an undeclared Board', closure: function (): void {
    configureLeague();
    config(['level-up.leaderboard.league.board' => 'missing-board']);

    tap(User::newFactory()->create())->addPoints(amount: 50);
})->throws(exception: BoardNotFoundException::class, exceptionMessage: "The league references the Board [missing-board], but no such Board is declared. Declare it under 'level-up.leaderboard.boards'.");

it(description: 'throws on first earn when the league references a Board without a period', closure: function (): void {
    configureLeague();
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['metric' => 'xp'],
    ]]);

    tap(User::newFactory()->create())->addPoints(amount: 50);
})->throws(exception: LeagueBoardNotPeriodicException::class, exceptionMessage: "The league references the Board [weekly-xp], but it does not declare a period. A League is a competitive cycle — declare a 'period' ('day', 'week', or 'month') on the Board, or bind the league to a periodic Board.");

it(description: 'throws on first earn when the league declares no divisions', closure: function (): void {
    configureLeague();
    config(['level-up.leaderboard.league.divisions' => []]);

    tap(User::newFactory()->create())->addPoints(amount: 50);
})->throws(exception: LeagueDivisionsNotDeclaredException::class, exceptionMessage: "The league declares no divisions. Declare the ladder bottom to top under 'level-up.leaderboard.league.divisions'.");
