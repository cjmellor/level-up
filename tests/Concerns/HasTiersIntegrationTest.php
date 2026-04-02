<?php

declare(strict_types=1);

use LevelUp\Experience\Exceptions\TierRequirementNotMet;
use LevelUp\Experience\Models\Achievement;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Tests\Fixtures\User;

uses()->group('tiers', 'integration');

beforeEach(closure: function (): void {
    config()->set('level-up.multiplier.enabled', false);
    config()->set('level-up.user.model', User::class);

    Tier::add(
        ['name' => 'Bronze', 'experience' => 0],
        ['name' => 'Silver', 'experience' => 500],
        ['name' => 'Gold', 'experience' => 2000],
    );
});

test(description: 'a tier-gated achievement can be granted when user meets the tier', closure: function (): void {
    $goldTier = Tier::query()->where('name', 'Gold')->first();
    $achievement = Achievement::factory()->create(['tier_id' => $goldTier->id]);

    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);

    $this->user->grantAchievement($achievement);

    expect($this->user->fresh())->getUserAchievements()->toHaveCount(1);
});

test(description: 'a tier-gated achievement throws when user does not meet the tier', closure: function (): void {
    $goldTier = Tier::query()->where('name', 'Gold')->first();
    $achievement = Achievement::factory()->create(['tier_id' => $goldTier->id]);

    $this->user->addPoints(amount: 100);

    $this->user->grantAchievement($achievement);
})->throws(exception: TierRequirementNotMet::class, exceptionMessage: 'User does not meet the required tier "Gold" for this achievement.');

test(description: 'an achievement without a tier can be granted to any user', closure: function (): void {
    $achievement = Achievement::factory()->create();

    $this->user->addPoints(amount: 10);
    $this->user->grantAchievement($achievement);

    expect($this->user->fresh())->getUserAchievements()->toHaveCount(1);
});

test(description: 'tier multiplier is applied when earning points', closure: function (): void {
    config()->set('level-up.tiers.multipliers', [
        'Bronze' => 1,
        'Silver' => 2,
    ]);

    $this->user->addPoints(amount: 550);

    $this->user->addPoints(amount: 50);

    expect($this->user->fresh()->getPoints())->toBe(expected: 650);
});

test(description: 'tier multiplier is not applied when no multiplier is configured', closure: function (): void {
    config()->set('level-up.tiers.multipliers', []);

    $this->user->addPoints(amount: 550);
    $this->user->addPoints(amount: 50);

    expect($this->user->fresh()->getPoints())->toBe(expected: 600);
});

test(description: 'streak freeze duration uses tier-specific days', closure: function (): void {
    config()->set('level-up.tiers.streak_freeze_days', [
        'Bronze' => 1,
        'Silver' => 3,
    ]);

    $activity = Activity::factory()->create();
    $this->user->addPoints(amount: 550);

    $this->user->recordStreak($activity);
    $this->user->freezeStreak($activity);

    $streak = $this->user->streaks()->whereBelongsTo($activity)->first();

    expect($streak->frozen_until)->toBeCarbon(
        expected: now()->addDays(3)->startOfDay()->toDateString()
    );
});

test(description: 'streak freeze falls back to global duration when tier has no override', closure: function (): void {
    config()->set('level-up.freeze_duration', 2);
    config()->set('level-up.tiers.streak_freeze_days', [
        'Gold' => 5,
    ]);

    $activity = Activity::factory()->create();
    $this->user->addPoints(amount: 10);

    $this->user->recordStreak($activity);
    $this->user->freezeStreak($activity);

    $streak = $this->user->streaks()->whereBelongsTo($activity)->first();

    expect($streak->frozen_until)->toBeCarbon(
        expected: now()->addDays(2)->startOfDay()->toDateString()
    );
});

test(description: 'leaderboard can be filtered by tier', closure: function (): void {
    $this->user->addPoints(amount: 550);

    $secondUser = User::query()->create([
        'name' => 'Other User',
        'email' => 'other@test.com',
        'password' => bcrypt('password'),
    ]);
    $secondUser->addPoints(amount: 100);

    $silverLeaderboard = resolve('leaderboard')->forTier('Silver')->generate();

    expect($silverLeaderboard)->toHaveCount(count: 1)
        ->and($silverLeaderboard->first()->id)->toBe(expected: $this->user->id);
});
