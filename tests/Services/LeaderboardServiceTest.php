<?php

declare(strict_types=1);

uses()->group('leaderboard');

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use LevelUp\Experience\Exceptions\MetricDisabledException;
use LevelUp\Experience\Exceptions\MetricNotFoundException;
use LevelUp\Experience\Facades\Leaderboard;
use LevelUp\Experience\Metrics\ExperienceMetric;
use LevelUp\Experience\Support\LeaderboardEntry;
use LevelUp\Experience\Tests\Fixtures\User;
use LevelUp\Experience\Tests\Fixtures\UserIdMetric;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'generates leaderboard entries with user and score, ordered by score descending', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);
    tap(User::newFactory()->create())->addPoints(198);
    tap(User::newFactory()->create())->addPoints(245);

    $entries = Leaderboard::generate();

    expect($entries)->toHaveCount(count: 4)
        ->and($entries->first())->toBeInstanceOf(class: LeaderboardEntry::class)
        ->and($entries->first()->user)->toBeInstanceOf(class: User::class)
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())
        ->toBe([245, 198, 123, 44]);
});

it(description: 'ranks by an explicitly selected metric key', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);

    $entries = Leaderboard::by(metric: 'xp')->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())
        ->toBe([123, 44]);
});

it(description: 'throws for an unknown metric key', closure: function (): void {
    Leaderboard::by(metric: 'nonsense');
})->throws(exception: MetricNotFoundException::class, exceptionMessage: 'nonsense');

it(description: 'ranks by a custom metric resolved from its class name', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(999);
    tap(User::newFactory()->create())->addPoints(1);

    $entries = Leaderboard::by(metric: UserIdMetric::class)->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int|float => $entry->score)->toArray())
        ->toBe([3, 2, 1]);
});

it(description: 'ranks by a custom metric instance', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(999);
    tap(User::newFactory()->create())->addPoints(1);

    $entries = Leaderboard::by(metric: new UserIdMetric())->generate();

    expect($entries->first()->score)->toBe(3);
});

it(description: 'uses the configured default metric for a bare generate call', closure: function (): void {
    config([
        'level-up.leaderboard.metrics.user-id' => UserIdMetric::class,
        'level-up.leaderboard.default_metric' => 'user-id',
    ]);

    tap(User::newFactory()->create())->addPoints(999);

    $entries = Leaderboard::generate();

    expect($entries)->toHaveCount(count: 2)
        ->and($entries->first()->score)->toBe(2);
});

it(description: 'throws when generating with a metric whose feature is disabled', closure: function (): void {
    Leaderboard::by(metric: new UserIdMetric(enabled: false))->generate();
})->throws(exception: MetricDisabledException::class, exceptionMessage: 'user-id');

it(description: 'paginates leaderboard entries', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);

    $paginated = Leaderboard::generate(paginate: true);

    expect($paginated)->toBeInstanceOf(class: LengthAwarePaginator::class)
        ->and($paginated->total())->toBe(expected: 2)
        ->and($paginated->items()[0])->toBeInstanceOf(class: LeaderboardEntry::class)
        ->and($paginated->items()[0]->score)->toBe(expected: 123);
});

it(description: 'limits the number of leaderboard entries', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);
    tap(User::newFactory()->create())->addPoints(198);

    $entries = Leaderboard::generate(limit: 2);

    expect($entries)->toHaveCount(count: 2)
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())
        ->toBe([198, 123]);
});

it(description: 'exposes a stable key and label on the experience metric', closure: function (): void {
    $metric = new ExperienceMetric();

    expect($metric->key())->toBe(expected: 'xp')
        ->and($metric->label())->toBe(expected: 'Experience')
        ->and($metric->enabled())->toBeTrue();
});

it(description: 'only includes users with experience points', closure: function (): void {
    tap(User::newFactory()->create());
    $userWithPoints = tap(User::newFactory()->create())->addPoints(44);

    $entries = Leaderboard::generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($userWithPoints->id);
});
