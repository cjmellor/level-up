<?php

uses()->group('leaderboard');

use LevelUp\Experience\Facades\Leaderboard;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function () {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'returns the correct data in the correct order', closure: function () {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);
    tap(User::newFactory()->create())->addPoints(198);
    tap(User::newFactory()->create())->addPoints(245);

    expect(
        Leaderboard::generate()
            ->pluck(value: 'experience.experience_points')
            ->toArray()
    )
        ->toBe([245, 198, 123, 44])
        ->toHaveCount(count: 4);
});

it(description: 'only shows users with experience points', closure: function () {
    tap(User::newFactory()->create());
    $userWithPoints = tap(User::newFactory()->create())->addPoints(44);

    expect(Leaderboard::generate())->toHaveCount(count: 1)
        ->and(Leaderboard::generate()->first()->id)->toEqual($userWithPoints->id);
});
