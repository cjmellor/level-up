<?php

declare(strict_types=1);

uses()->group('league');

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Enums\DivisionDirection;
use LevelUp\Experience\Events\UserDivisionChanged;
use LevelUp\Experience\Models\Cohort;
use LevelUp\Experience\Models\Division;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);

    $this->travelTo(Date::parse(time: '2026-06-03 12:00:00'));
});

/**
 * @param  array<string, array{promote: int, relegate: int}>|null  $divisions
 */
function configureRolloverLeague(?array $divisions = null, int $cohortSize = 30): void
{
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['metric' => 'xp', 'period' => 'week'],
    ]]);

    config(['level-up.leaderboard.league' => [
        'board' => 'weekly-xp',
        'cohort_size' => $cohortSize,
        'divisions' => $divisions ?? [
            'Bronze' => ['promote' => 2, 'relegate' => 0],
            'Silver' => ['promote' => 2, 'relegate' => 2],
            'Gold' => ['promote' => 0, 'relegate' => 2],
        ],
    ]]);
}

/**
 * @return array<int, Division>
 */
function seedRolloverLadder(string ...$names): array
{
    return collect(value: $names)
        ->map(callback: fn (string $name, int $index): Division => Division::query()->create(attributes: ['name' => $name, 'position' => $index + 1]))
        ->all();
}

/**
 * @return array<int, User>
 */
function seedWeekCohort(Division $division, int ...$scores): array
{
    $cohort = Cohort::query()->create(attributes: [
        'division_id' => $division->id,
        'period_start' => Date::parse(time: '2026-06-01 00:00:00'),
        'period_end' => Date::parse(time: '2026-06-08 00:00:00'),
    ]);

    return collect(value: $scores)
        ->map(function (int $score) use ($cohort): User {
            $user = User::newFactory()->create();
            $cohort->users()->attach(ids: $user->id);
            $user->addPoints(amount: $score);

            return $user;
        })
        ->all();
}

it(description: 'promotes the top finishers of a closed cohort up a division', closure: function (): void {
    configureRolloverLeague();
    [$bronze] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    [$first, $second, $third, $fourth] = seedWeekCohort($bronze, 40, 30, 20, 10);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect($first->currentDivision()?->name)->toBe(expected: 'Silver')
        ->and($second->currentDivision()?->name)->toBe(expected: 'Silver')
        ->and($third->currentDivision()?->name)->toBe(expected: 'Bronze')
        ->and($fourth->currentDivision()?->name)->toBe(expected: 'Bronze');

    Event::assertDispatchedTimes(event: UserDivisionChanged::class, times: 2);
});

it(description: 'relegates the bottom finishers of a closed cohort down a division', closure: function (): void {
    configureRolloverLeague();
    [, $silver] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    [$first, $second, $third, $fourth] = seedWeekCohort($silver, 40, 30, 20, 10);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect($first->currentDivision()?->name)->toBe(expected: 'Gold')
        ->and($second->currentDivision()?->name)->toBe(expected: 'Gold')
        ->and($third->currentDivision()?->name)->toBe(expected: 'Bronze')
        ->and($fourth->currentDivision()?->name)->toBe(expected: 'Bronze');

    Event::assertDispatched(event: UserDivisionChanged::class, callback: fn (UserDivisionChanged $event): bool => $event->user->is($fourth) && $event->direction === DivisionDirection::Relegated);
});

it(description: 'keeps middle finishers in their division with no event', closure: function (): void {
    configureRolloverLeague(divisions: [
        'Bronze' => ['promote' => 1, 'relegate' => 0],
        'Silver' => ['promote' => 1, 'relegate' => 1],
        'Gold' => ['promote' => 0, 'relegate' => 1],
    ]);
    [, $silver] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    [$first, $second, $third, $fourth] = seedWeekCohort($silver, 40, 30, 20, 10);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect($second->currentDivision()?->name)->toBe(expected: 'Silver')
        ->and($third->currentDivision()?->name)->toBe(expected: 'Silver')
        ->and(DB::table(table: 'cohort_user')->where(column: 'user_id', operator: '=', value: $second->id)->value(column: 'next_division_id'))->toBeNull();

    Event::assertDispatchedTimes(event: UserDivisionChanged::class, times: 2);
    Event::assertNotDispatched(event: UserDivisionChanged::class, callback: fn (UserDivisionChanged $event): bool => $event->user->is($second) || $event->user->is($third));
});

it(description: 'fires UserDivisionChanged carrying the user, board, divisions, and direction', closure: function (): void {
    configureRolloverLeague();
    [$bronze, $silver] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    [$first] = seedWeekCohort($bronze, 40, 30, 20);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    Event::assertDispatched(event: UserDivisionChanged::class, callback: fn (UserDivisionChanged $event): bool => $event->user->is($first)
        && $event->board === 'weekly-xp'
        && $event->previousDivision->is($bronze)
        && $event->newDivision->is($silver)
        && $event->direction === DivisionDirection::Promoted);
});

it(description: 'clamps promotion to the cohort size', closure: function (): void {
    configureRolloverLeague();
    [$bronze] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    [$only] = seedWeekCohort($bronze, 50);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect($only->currentDivision()?->name)->toBe(expected: 'Silver');

    Event::assertDispatchedTimes(event: UserDivisionChanged::class, times: 1);
});

it(description: 'promotes the whole cohort when it is no larger than its promote count', closure: function (): void {
    configureRolloverLeague();
    [, $silver] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    [$first, $second] = seedWeekCohort($silver, 40, 10);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect($first->currentDivision()?->name)->toBe(expected: 'Gold')
        ->and($second->currentDivision()?->name)->toBe(expected: 'Gold');

    Event::assertDispatchedTimes(event: UserDivisionChanged::class, times: 2);
    Event::assertNotDispatched(event: UserDivisionChanged::class, callback: fn (UserDivisionChanged $event): bool => $event->direction === DivisionDirection::Relegated);
});

it(description: 'never relegates a user it promoted — promotion wins', closure: function (): void {
    configureRolloverLeague();
    [, $silver] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    [$first, $second, $third] = seedWeekCohort($silver, 30, 20, 10);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect($first->currentDivision()?->name)->toBe(expected: 'Gold')
        ->and($second->currentDivision()?->name)->toBe(expected: 'Gold')
        ->and($third->currentDivision()?->name)->toBe(expected: 'Bronze');

    Event::assertDispatchedTimes(event: UserDivisionChanged::class, times: 3);
    Event::assertNotDispatched(event: UserDivisionChanged::class, callback: fn (UserDivisionChanged $event): bool => $event->user->is($second) && $event->direction === DivisionDirection::Relegated);
});

it(description: 'promotes no one from the top division and relegates no one from the bottom', closure: function (): void {
    configureRolloverLeague(divisions: [
        'Bronze' => ['promote' => 1, 'relegate' => 3],
        'Gold' => ['promote' => 3, 'relegate' => 1],
    ]);
    [$bronze, $gold] = seedRolloverLadder('Bronze', 'Gold');
    [$bronzeTop, $bronzeMiddle, $bronzeBottom] = seedWeekCohort($bronze, 60, 50, 40);
    [, , $goldBottom] = seedWeekCohort($gold, 30, 20, 10);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect($bronzeTop->currentDivision()?->name)->toBe(expected: 'Gold')
        ->and($bronzeMiddle->currentDivision()?->name)->toBe(expected: 'Bronze')
        ->and($bronzeBottom->currentDivision()?->name)->toBe(expected: 'Bronze')
        ->and($goldBottom->currentDivision()?->name)->toBe(expected: 'Bronze');

    Event::assertDispatchedTimes(event: UserDivisionChanged::class, times: 2);
    Event::assertNotDispatched(event: UserDivisionChanged::class, callback: fn (UserDivisionChanged $event): bool => $event->direction === DivisionDirection::Relegated && $event->previousDivision->is($bronze));
    Event::assertNotDispatched(event: UserDivisionChanged::class, callback: fn (UserDivisionChanged $event): bool => $event->direction === DivisionDirection::Promoted && $event->previousDivision->is($gold));
});

it(description: 'leaves ghosts in their held division untouched', closure: function (): void {
    configureRolloverLeague();
    [$bronze, $silver] = seedRolloverLadder('Bronze', 'Silver', 'Gold');

    $ghost = User::newFactory()->create();
    $pastCohort = Cohort::query()->create(attributes: [
        'division_id' => $silver->id,
        'period_start' => Date::parse(time: '2026-05-25 00:00:00'),
        'period_end' => Date::parse(time: '2026-06-01 00:00:00'),
        'rolled_over_at' => Date::parse(time: '2026-06-01 00:05:00'),
    ]);
    $pastCohort->users()->attach(ids: $ghost->id);

    seedWeekCohort($bronze, 50);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect($ghost->currentDivision()?->name)->toBe(expected: 'Silver')
        ->and($ghost->currentCohort())->toBeNull();

    Event::assertNotDispatched(event: UserDivisionChanged::class, callback: fn (UserDivisionChanged $event): bool => $event->user->is($ghost));
});

it(description: 'is idempotent — re-running for a rolled-over period is a no-op', closure: function (): void {
    configureRolloverLeague();
    [$bronze] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    seedWeekCohort($bronze, 40, 30, 20, 10);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();
    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect(Cohort::query()->first()?->rolled_over_at)->toBeCarbon(expected: '2026-06-10 12:00:00');

    Event::assertDispatchedTimes(event: UserDivisionChanged::class, times: 2);
});

it(description: 'does not roll over a cohort whose period is still open', closure: function (): void {
    configureRolloverLeague();
    [$bronze] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    [$first] = seedWeekCohort($bronze, 40, 30);

    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect(Cohort::query()->first()?->rolled_over_at)->toBeNull()
        ->and($first->currentDivision()?->name)->toBe(expected: 'Bronze');

    Event::assertNotDispatched(event: UserDivisionChanged::class);
});

it(description: 'stays dormant when no league is configured', closure: function (): void {
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect(Cohort::query()->count())->toBe(expected: 0);

    Event::assertNotDispatched(event: UserDivisionChanged::class);
});

it(description: 'enrolls a moved user into their new division on their next earn', closure: function (): void {
    configureRolloverLeague(divisions: [
        'Bronze' => ['promote' => 1, 'relegate' => 0],
        'Silver' => ['promote' => 1, 'relegate' => 1],
        'Gold' => ['promote' => 0, 'relegate' => 1],
    ]);
    [$bronze] = seedRolloverLadder('Bronze', 'Silver', 'Gold');
    [$promoted, $stayer] = seedWeekCohort($bronze, 40, 10);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    $promoted->addPoints(amount: 25);
    $stayer->addPoints(amount: 25);

    expect($promoted->currentCohort()?->division->name)->toBe(expected: 'Silver')
        ->and($stayer->currentCohort()?->division->name)->toBe(expected: 'Bronze');
});

it(description: 'splits a tie straddling the promote boundary by standings order, not rank number', closure: function (): void {
    configureRolloverLeague(divisions: [
        'Bronze' => ['promote' => 1, 'relegate' => 0],
        'Silver' => ['promote' => 0, 'relegate' => 1],
    ]);
    [$bronze] = seedRolloverLadder('Bronze', 'Silver');
    [$earlier, $later] = seedWeekCohort($bronze, 50, 50);

    $this->travelTo(Date::parse(time: '2026-06-10 12:00:00'));
    Event::fake(eventsToFake: [UserDivisionChanged::class]);

    $this->artisan(command: 'level-up:league-rollover')->assertSuccessful();

    expect($earlier->currentDivision()?->name)->toBe(expected: 'Silver')
        ->and($later->currentDivision()?->name)->toBe(expected: 'Bronze');

    Event::assertDispatchedTimes(event: UserDivisionChanged::class, times: 1);
});
