<?php

declare(strict_types=1);

uses()->group('leaderboard');

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\LeaderboardRankChanged;
use LevelUp\Experience\Events\UserEnteredTrackedDepth;
use LevelUp\Experience\Events\UserLeftTrackedDepth;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\LeaderboardSnapshot;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'snapshots the top entries of a declared Board', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
    ]]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    $leader = tap(User::newFactory()->create())->addPoints(300);
    $runnerUp = tap(User::newFactory()->create())->addPoints(100);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $snapshots = LeaderboardSnapshot::query()->orderBy(column: 'rank')->get();

    expect($snapshots)->toHaveCount(count: 2)
        ->and($snapshots->first()->board)->toBe(expected: 'xp-board')
        ->and($snapshots->first()->user_id)->toEqual($leader->id)
        ->and($snapshots->first()->rank)->toBe(expected: 1)
        ->and($snapshots->first()->score)->toBe(expected: 300.0)
        ->and($snapshots->first()->run_at)->toBeCarbon(expected: '2026-06-01 06:00:00')
        ->and($snapshots->last()->user_id)->toEqual($runnerUp->id)
        ->and($snapshots->last()->rank)->toBe(expected: 2);
});

it(description: 'stores no snapshot rows below the default tracked depth of 100', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
    ]]);

    $users = User::newFactory()->count(count: 101)->create();

    $users->each(function (User $user, int $index): void {
        Experience::query()->create(attributes: [
            'user_id' => $user->id,
            'level_id' => 1,
            'experience_points' => 1000 - $index,
        ]);
    });

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $hindmost = $users->last();

    expect(LeaderboardSnapshot::query()->count())->toBe(expected: 100)
        ->and(LeaderboardSnapshot::query()->where(column: 'user_id', operator: '=', value: $hindmost->id)->exists())->toBeFalse();
});

it(description: 'bounds the snapshot by a per-Board track_top override', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp', 'track_top' => 2],
    ]]);

    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(200);
    $belowDepth = tap(User::newFactory()->create())->addPoints(100);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    expect(LeaderboardSnapshot::query()->count())->toBe(expected: 2)
        ->and(LeaderboardSnapshot::query()->where(column: 'user_id', operator: '=', value: $belowDepth->id)->exists())->toBeFalse();
});

it(description: 'dispatches rank-changed events with from and to ranks when a second run diffs against the first', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
    ]]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    $overtaken = tap(User::newFactory()->create())->addPoints(100);
    $climber = tap(User::newFactory()->create())->addPoints(50);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    $climber->addPoints(100);

    Event::fake(eventsToFake: [LeaderboardRankChanged::class]);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    Event::assertDispatched(
        event: LeaderboardRankChanged::class,
        callback: fn (LeaderboardRankChanged $event): bool => $event->user->is(model: $climber)
            && $event->board === 'xp-board'
            && $event->from === 2
            && $event->to === 1,
    );
    Event::assertDispatched(
        event: LeaderboardRankChanged::class,
        callback: fn (LeaderboardRankChanged $event): bool => $event->user->is(model: $overtaken)
            && $event->board === 'xp-board'
            && $event->from === 1
            && $event->to === 2,
    );
    Event::assertDispatchedTimes(event: LeaderboardRankChanged::class, times: 2);
});

it(description: 'dispatches an entered event when a user breaks into the tracked depth', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp', 'track_top' => 2],
    ]]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    tap(User::newFactory()->create())->addPoints(300);
    $displaced = tap(User::newFactory()->create())->addPoints(200);
    $climber = tap(User::newFactory()->create())->addPoints(100);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    $climber->addPoints(150);

    Event::fake(eventsToFake: [UserEnteredTrackedDepth::class, UserLeftTrackedDepth::class]);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    Event::assertDispatched(
        event: UserEnteredTrackedDepth::class,
        callback: fn (UserEnteredTrackedDepth $event): bool => $event->user->is(model: $climber)
            && $event->board === 'xp-board'
            && $event->rank === 2,
    );
    Event::assertDispatchedTimes(event: UserEnteredTrackedDepth::class, times: 1);
    Event::assertDispatched(
        event: UserLeftTrackedDepth::class,
        callback: fn (UserLeftTrackedDepth $event): bool => $event->user->is(model: $displaced)
            && $event->board === 'xp-board'
            && $event->previousRank === 2,
    );
    Event::assertDispatchedTimes(event: UserLeftTrackedDepth::class, times: 1);
});

it(description: 'stays silent for rank shuffles below the tracked depth', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp', 'track_top' => 2],
    ]]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(200);
    $midTable = tap(User::newFactory()->create())->addPoints(100);
    tap(User::newFactory()->create())->addPoints(50);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    $midTable->addPoints(20);

    Event::fake(eventsToFake: [LeaderboardRankChanged::class, UserEnteredTrackedDepth::class, UserLeftTrackedDepth::class]);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    Event::assertNotDispatched(event: LeaderboardRankChanged::class);
    Event::assertNotDispatched(event: UserEnteredTrackedDepth::class);
    Event::assertNotDispatched(event: UserLeftTrackedDepth::class);
});

it(description: 'dispatches nothing when a run finds no rank movement', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
    ]]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(100);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));

    Event::fake(eventsToFake: [LeaderboardRankChanged::class, UserEnteredTrackedDepth::class, UserLeftTrackedDepth::class]);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    Event::assertNotDispatched(event: LeaderboardRankChanged::class);
    Event::assertNotDispatched(event: UserEnteredTrackedDepth::class);
    Event::assertNotDispatched(event: UserLeftTrackedDepth::class);
});

it(description: 'prunes snapshot runs older than the configured retention', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
    ]]);

    tap(User::newFactory()->create())->addPoints(300);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));
    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-16 06:00:00'));
    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-07-02 06:00:00'));
    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $remainingRuns = LeaderboardSnapshot::query()->pluck(column: 'run_at')->map(fn ($runAt): string => $runAt->format('Y-m-d H:i:s'));

    expect($remainingRuns->all())->toBe(['2026-06-16 06:00:00', '2026-07-02 06:00:00']);
});

it(description: 'respects a custom retention_days config when pruning', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
    ]]);
    config(['level-up.leaderboard.snapshots.retention_days' => 7]);

    tap(User::newFactory()->create())->addPoints(300);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));
    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-09 06:00:00'));
    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $remainingRuns = LeaderboardSnapshot::query()->pluck(column: 'run_at')->map(fn ($runAt): string => $runAt->format('Y-m-d H:i:s'));

    expect($remainingRuns->all())->toBe(['2026-06-09 06:00:00']);
});

it(description: 'dispatches nothing on the first run of a Board because there is no previous run to diff', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
    ]]);

    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(100);

    Event::fake(eventsToFake: [LeaderboardRankChanged::class, UserEnteredTrackedDepth::class, UserLeftTrackedDepth::class]);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    expect(LeaderboardSnapshot::query()->count())->toBe(expected: 2);

    Event::assertNotDispatched(event: LeaderboardRankChanged::class);
    Event::assertNotDispatched(event: UserEnteredTrackedDepth::class);
    Event::assertNotDispatched(event: UserLeftTrackedDepth::class);
});

it(description: 'diffs each declared Board in isolation', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
        'level-board' => ['metric' => 'level'],
    ]]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    $overtaken = tap(User::newFactory()->create())->addPoints(200);
    $climber = tap(User::newFactory()->create())->addPoints(180);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    $climber->addPoints(30);

    Event::fake(eventsToFake: [LeaderboardRankChanged::class, UserEnteredTrackedDepth::class, UserLeftTrackedDepth::class]);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    expect(LeaderboardSnapshot::query()->where(column: 'board', operator: '=', value: 'xp-board')->count())->toBe(expected: 4)
        ->and(LeaderboardSnapshot::query()->where(column: 'board', operator: '=', value: 'level-board')->count())->toBe(expected: 4);

    Event::assertDispatched(
        event: LeaderboardRankChanged::class,
        callback: fn (LeaderboardRankChanged $event): bool => $event->board === 'xp-board',
    );
    Event::assertNotDispatched(
        event: LeaderboardRankChanged::class,
        callback: fn (LeaderboardRankChanged $event): bool => $event->board === 'level-board',
    );
    Event::assertNotDispatched(event: UserEnteredTrackedDepth::class);
    Event::assertNotDispatched(event: UserLeftTrackedDepth::class);
});

it(description: 'replaces the rows of a same-instant run instead of duplicating them', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
    ]]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(100);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();
    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $snapshots = LeaderboardSnapshot::query()->orderBy(column: 'rank')->get();

    expect($snapshots)->toHaveCount(count: 2)
        ->and($snapshots->map(fn (LeaderboardSnapshot $snapshot): int => $snapshot->rank)->all())->toBe([1, 2]);
});

it(description: 'reports only real movement when a tie breaks', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'xp-board' => ['metric' => 'xp'],
    ]]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    $slipped = tap(User::newFactory()->create())->addPoints(100);
    $tieBreaker = tap(User::newFactory()->create())->addPoints(100);
    tap(User::newFactory()->create())->addPoints(90);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    $tieBreaker->addPoints(50);

    Event::fake(eventsToFake: [LeaderboardRankChanged::class, UserEnteredTrackedDepth::class, UserLeftTrackedDepth::class]);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    Event::assertDispatched(
        event: LeaderboardRankChanged::class,
        callback: fn (LeaderboardRankChanged $event): bool => $event->user->is(model: $slipped)
            && $event->from === 1
            && $event->to === 2,
    );
    Event::assertDispatchedTimes(event: LeaderboardRankChanged::class, times: 1);
    Event::assertNotDispatched(event: UserEnteredTrackedDepth::class);
    Event::assertNotDispatched(event: UserLeftTrackedDepth::class);
});
