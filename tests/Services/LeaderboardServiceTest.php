<?php

uses()->group('leaderboard');

use LevelUp\Experience\Services\LeaderboardService;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function () {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'returns the correct data in the correct order', closure: function () {
    // A User is also created in Pest.php, so we have 5 Users in total.
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);
    tap(User::newFactory()->create())->addPoints(198);
    tap(User::newFactory()->create())->addPoints(245);

    expect(
        app(abstract: LeaderboardService::class)
            ->generate()
            ->pluck(value: 'experience.experience_points')
            ->toArray()
    )
        ->toBe([245, 198, 123, 44, null])
        ->toHaveCount(count: 5);
});
