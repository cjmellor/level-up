<?php

declare(strict_types=1);

uses()->group('leaderboard');

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use LevelUp\Experience\Enums\Period;
use LevelUp\Experience\Facades\Leaderboard;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Support\LeaderboardEntry;
use LevelUp\Experience\Tests\Fixtures\User;
use LevelUp\Experience\Tests\Fixtures\UserIdMetric;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'restricts the board to the users matched by the host closure', closure: function (): void {
    $friendOne = tap(User::newFactory()->create())->addPoints(100);
    tap(User::newFactory()->create())->addPoints(300);
    $friendTwo = tap(User::newFactory()->create())->addPoints(200);

    $entries = Leaderboard::restrictTo(
        constraint: fn (Builder $query): Builder => $query->whereIn(column: 'id', values: [$friendOne->id, $friendTwo->id]),
    )->generate();

    expect($entries)->toHaveCount(count: 2)
        ->and($entries->map(fn (LeaderboardEntry $entry): mixed => $entry->user->id)->toArray())
        ->toBe([$friendTwo->id, $friendOne->id]);
});

it(description: 'computes ranks within the restricted set, not the global board', closure: function (): void {
    collect(value: [500, 400, 300, 200])
        ->each(fn (int $points): User => tap(User::newFactory()->create())->addPoints($points));

    $topFriend = tap(User::newFactory()->create())->addPoints(100);
    $bottomFriend = tap(User::newFactory()->create())->addPoints(50);

    expect(Leaderboard::rankOf(user: $topFriend))->toBe(expected: 5);

    $entries = Leaderboard::restrictTo(
        constraint: fn (Builder $query): Builder => $query->whereIn(column: 'id', values: [$topFriend->id, $bottomFriend->id]),
    )->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())->toBe([1, 2])
        ->and($entries->first()->user->id)->toEqual($topFriend->id);
});

it(description: 'composes a restriction with an explicitly selected metric', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(999);
    $friend = tap(User::newFactory()->create())->addPoints(1);

    $entries = Leaderboard::by(metric: UserIdMetric::class)
        ->restrictTo(constraint: fn (Builder $query): Builder => $query->whereIn(column: 'id', values: [$this->user->id, $friend->id]))
        ->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): mixed => $entry->user->id)->toArray())
        ->toBe([$friend->id, $this->user->id])
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())->toBe([1, 2]);
});

it(description: 'composes a restriction with a periodic board', closure: function (): void {
    $this->travelTo(Date::parse(time: '2026-06-03 12:00:00'));
    $pastFriend = tap(User::newFactory()->create())->addPoints(500);

    $this->travelTo(Date::parse(time: '2026-06-05 12:00:00'));
    $todayFriend = tap(User::newFactory()->create())->addPoints(40);
    tap(User::newFactory()->create())->addPoints(900);

    $entries = Leaderboard::period(period: Period::Day)
        ->restrictTo(constraint: fn (Builder $query): Builder => $query->whereIn(column: 'id', values: [$pastFriend->id, $todayFriend->id]))
        ->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($todayFriend->id)
        ->and($entries->first()->rank)->toBe(expected: 1)
        ->and($entries->first()->score)->toBe(expected: 40);
});

it(description: 'composes a restriction with a tier filter', closure: function (): void {
    Tier::add(
        ['name' => 'Bronze', 'experience' => 0],
        ['name' => 'Silver', 'experience' => 500],
    );

    $silverFriend = tap(User::newFactory()->create())->addPoints(550);
    tap(User::newFactory()->create())->addPoints(560);
    $bronzeFriend = tap(User::newFactory()->create())->addPoints(100);

    $entries = Leaderboard::forTier(tier: 'Silver')
        ->restrictTo(constraint: fn (Builder $query): Builder => $query->whereIn(column: 'id', values: [$silverFriend->id, $bronzeFriend->id]))
        ->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($silverFriend->id)
        ->and($entries->first()->rank)->toBe(expected: 1);
});

it(description: 'returns a rank within the restricted set from rankOf, and null for a user outside it', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(500);
    $stranger = tap(User::newFactory()->create())->addPoints(400);
    $topFriend = tap(User::newFactory()->create())->addPoints(300);
    $bottomFriend = tap(User::newFactory()->create())->addPoints(200);

    $friends = fn (Builder $query): Builder => $query->whereIn(column: 'id', values: [$topFriend->id, $bottomFriend->id]);

    expect(Leaderboard::restrictTo(constraint: $friends)->rankOf(user: $topFriend))->toBe(expected: 1)
        ->and(Leaderboard::restrictTo(constraint: $friends)->rankOf(user: $bottomFriend))->toBe(expected: 2)
        ->and(Leaderboard::restrictTo(constraint: $friends)->rankOf(user: $stranger))->toBeNull();
});

it(description: 'returns the entries around a user within the restricted set', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(900);

    $friends = collect(value: [500, 400, 300, 200, 100])
        ->map(fn (int $points): User => tap(User::newFactory()->create())->addPoints($points));

    $friendIds = $friends->map(fn (User $friend): mixed => $friend->id)->toArray();

    $entries = Leaderboard::restrictTo(constraint: fn (Builder $query): Builder => $query->whereIn(column: 'id', values: $friendIds))
        ->around(user: $friends[2], range: 1);

    expect($entries)->toHaveCount(count: 3)
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())->toBe([2, 3, 4])
        ->and($entries[1]->user->id)->toEqual($friends[2]->id);
});

it(description: 'consumes the restriction so the next board is global again', closure: function (): void {
    $friend = tap(User::newFactory()->create())->addPoints(100);
    tap(User::newFactory()->create())->addPoints(200);

    $restricted = Leaderboard::restrictTo(constraint: fn (Builder $query): Builder => $query->whereKey(id: $friend->id))->generate();
    $global = Leaderboard::generate();

    expect($restricted)->toHaveCount(count: 1)
        ->and($global)->toHaveCount(count: 2);
});
