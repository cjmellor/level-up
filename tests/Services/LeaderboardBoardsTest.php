<?php

declare(strict_types=1);

uses()->group('leaderboard');

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use LevelUp\Experience\Enums\Period;
use LevelUp\Experience\Exceptions\BoardNotFoundException;
use LevelUp\Experience\Exceptions\MetricNotFoundException;
use LevelUp\Experience\Exceptions\MetricNotWindowableException;
use LevelUp\Experience\Facades\Leaderboard;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Support\LeaderboardEntry;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'resolves a declared Board to its metric and generates entries', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'all-time-levels' => ['metric' => 'level'],
    ]]);

    tap(User::newFactory()->create())->addPoints(120);
    $leader = tap(User::newFactory()->create())->addPoints(300);

    $entries = Leaderboard::board(name: 'all-time-levels')->generate();

    expect($entries)->toHaveCount(count: 2)
        ->and($entries->first())->toBeInstanceOf(class: LeaderboardEntry::class)
        ->and($entries->first()->user->id)->toEqual($leader->id)
        ->and($entries->map(fn (LeaderboardEntry $entry): int|float => $entry->score)->toArray())->toBe([3, 2]);
});

it(description: 'windows a Board with a declared period to activity inside it', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['metric' => 'xp', 'period' => 'week'],
    ]]);

    $this->travelTo(Date::parse(time: '2026-05-20 12:00:00'));
    $pastEarner = tap(User::newFactory()->create())->addPoints(500);

    $this->travelTo(Date::parse(time: '2026-06-03 12:00:00'));
    $thisWeekEarner = tap(User::newFactory()->create())->addPoints(70);

    $entries = Leaderboard::board(name: 'weekly-xp')->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($thisWeekEarner->id)
        ->and($entries->first()->score)->toBe(expected: 70)
        ->and(Leaderboard::board(name: 'weekly-xp')->rankOf(user: $pastEarner))->toBeNull();
});

it(description: 'filters a Board with a declared tier to users in that tier', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'silver-race' => ['metric' => 'xp', 'tier' => 'Silver'],
    ]]);

    Tier::add(
        ['name' => 'Bronze', 'experience' => 0],
        ['name' => 'Silver', 'experience' => 500],
    );

    $silverUser = tap(User::newFactory()->create())->addPoints(550);
    tap(User::newFactory()->create())->addPoints(100);

    $entries = Leaderboard::board(name: 'silver-race')->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($silverUser->id)
        ->and($entries->first()->rank)->toBe(expected: 1);
});

it(description: 'throws a descriptive exception for an unknown Board name', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['metric' => 'xp', 'period' => 'week'],
    ]]);

    Leaderboard::board(name: 'monthly-xp');
})->throws(exception: BoardNotFoundException::class, exceptionMessage: "No leaderboard Board is declared for name [monthly-xp]. Declare it under 'level-up.leaderboard.boards'.");

it(description: 'throws at resolution when a Board declares a period for a non-Windowable metric', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'weekly-levels' => ['metric' => 'level', 'period' => 'week'],
    ]]);

    Leaderboard::board(name: 'weekly-levels');
})->throws(exception: MetricNotWindowableException::class, exceptionMessage: "The Board [weekly-levels] declares a period, but the [level] metric ranks by current state and does not support time Periods. Remove the 'period' key or declare a Windowable metric such as [xp].");

it(description: 'throws when a Board declares an unknown metric key', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'weekly-karma' => ['metric' => 'karma', 'period' => 'week'],
    ]]);

    Leaderboard::board(name: 'weekly-karma');
})->throws(exception: MetricNotFoundException::class, exceptionMessage: 'No leaderboard metric is registered for key [karma]');

it(description: 'throws when a Board does not declare a metric', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['period' => 'week'],
    ]]);

    Leaderboard::board(name: 'weekly-xp');
})->throws(exception: MetricNotFoundException::class, exceptionMessage: "The Board [weekly-xp] does not declare a metric. Every Board under 'level-up.leaderboard.boards' must declare a 'metric' key.");

it(description: 'accepts a Period enum instance as a declared period', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['metric' => 'xp', 'period' => Period::Week],
    ]]);

    $this->travelTo(Date::parse(time: '2026-05-20 12:00:00'));
    tap(User::newFactory()->create())->addPoints(500);

    $this->travelTo(Date::parse(time: '2026-06-03 12:00:00'));
    $thisWeekEarner = tap(User::newFactory()->create())->addPoints(70);

    $entries = Leaderboard::board(name: 'weekly-xp')->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($thisWeekEarner->id);
});

it(description: 'throws when a Board declares an invalid period value', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['metric' => 'xp', 'period' => 'fortnight'],
    ]]);

    Leaderboard::board(name: 'weekly-xp');
})->throws(exception: ValueError::class, exceptionMessage: 'not a valid backing value for enum');

it(description: 'throws at resolution when a Board declares a tier that does not exist', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'gold-race' => ['metric' => 'xp', 'period' => 'week', 'tier' => 'Gold'],
    ]]);

    Leaderboard::board(name: 'gold-race');
})->throws(exception: ModelNotFoundException::class);

it(description: 'composes fluent refinements on top of a resolved Board', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['metric' => 'xp', 'period' => 'week'],
    ]]);

    $friend = tap(User::newFactory()->create())->addPoints(50);
    tap(User::newFactory()->create())->addPoints(900);

    $entries = Leaderboard::board(name: 'weekly-xp')
        ->restrictTo(constraint: fn (Builder $query): Builder => $query->whereKey(id: $friend->id))
        ->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($friend->id)
        ->and($entries->first()->rank)->toBe(expected: 1);
});

it(description: 'answers rankOf and around from a resolved Board', closure: function (): void {
    config(['level-up.leaderboard.boards' => [
        'weekly-xp' => ['metric' => 'xp', 'period' => 'week'],
    ]]);

    $first = tap(User::newFactory()->create())->addPoints(300);
    $second = tap(User::newFactory()->create())->addPoints(200);
    $third = tap(User::newFactory()->create())->addPoints(100);

    expect(Leaderboard::board(name: 'weekly-xp')->rankOf(user: $second))->toBe(expected: 2);

    $around = Leaderboard::board(name: 'weekly-xp')->around(user: $second, range: 1);

    expect($around)->toHaveCount(count: 3)
        ->and($around->map(fn (LeaderboardEntry $entry): mixed => $entry->user->id)->toArray())
        ->toBe([$first->id, $second->id, $third->id]);
});
