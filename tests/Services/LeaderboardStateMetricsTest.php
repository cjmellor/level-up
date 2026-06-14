<?php

declare(strict_types=1);

uses()->group('leaderboard');

use LevelUp\Experience\Exceptions\MetricRequiresActivityException;
use LevelUp\Experience\Facades\Leaderboard;
use LevelUp\Experience\Metrics\LevelMetric;
use LevelUp\Experience\Metrics\StreakMetric;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Support\LeaderboardEntry;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'ranks users by their current level', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(50);
    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(120);

    $entries = Leaderboard::by(metric: 'level')->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())
        ->toBe([3, 2, 1]);
});

it(description: 'omits users without a level from the level board', closure: function (): void {
    $levelled = tap(User::newFactory()->create())->addPoints(120);

    $entries = Leaderboard::by(metric: 'level')->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($levelled->id);
});

it(description: 'shares a rank between users on the same level', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(120);
    tap(User::newFactory()->create())->addPoints(150);

    $entries = Leaderboard::by(metric: 'level')->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())
        ->toBe([1, 2, 2]);
});

it(description: 'ranks users by their current streak count for an activity', closure: function (): void {
    $activity = Activity::factory()->create();

    User::newFactory()->create()->streaks()->create(['activity_id' => $activity->id, 'count' => 3, 'activity_at' => now()]);
    User::newFactory()->create()->streaks()->create(['activity_id' => $activity->id, 'count' => 12, 'activity_at' => now()]);
    User::newFactory()->create()->streaks()->create(['activity_id' => $activity->id, 'count' => 7, 'activity_at' => now()]);

    $entries = Leaderboard::by(metric: new StreakMetric(activity: $activity))->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())
        ->toBe([12, 7, 3]);
});

it(description: 'omits users without a streak for the activity from the streak board', closure: function (): void {
    $activity = Activity::factory()->create();
    $otherActivity = Activity::factory()->create();

    $streaker = User::newFactory()->create();
    $streaker->streaks()->create(['activity_id' => $activity->id, 'count' => 4, 'activity_at' => now()]);

    User::newFactory()->create()->streaks()->create(['activity_id' => $otherActivity->id, 'count' => 9, 'activity_at' => now()]);

    $entries = Leaderboard::by(metric: new StreakMetric(activity: $activity))->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($streaker->id)
        ->and($entries->first()->score)->toBe(expected: 4);
});

it(description: 'throws when generating a streak board without an activity', closure: function (): void {
    Leaderboard::by(metric: 'streak')->generate();
})->throws(exception: MetricRequiresActivityException::class, exceptionMessage: 'streak');

it(description: 'exposes a stable key and label on the level metric', closure: function (): void {
    $metric = new LevelMetric();

    expect($metric->key())->toBe(expected: 'level')
        ->and($metric->label())->toBe(expected: 'Level')
        ->and($metric->enabled())->toBeTrue();
});

it(description: 'exposes a stable key and label on the streak metric', closure: function (): void {
    $metric = new StreakMetric(activity: Activity::factory()->create());

    expect($metric->key())->toBe(expected: 'streak')
        ->and($metric->label())->toBe(expected: 'Streak')
        ->and($metric->enabled())->toBeTrue();
});
