<?php

declare(strict_types=1);

use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Challenge;
use LevelUp\Experience\Tests\Fixtures\AliasingUser;

uses()
    ->group('aliasing')
    ->beforeEach(function (): void {
        config()->set(key: 'level-up.multiplier.enabled', value: false);
        config()->set(key: 'level-up.tiers.enabled', value: false);
        config()->set(key: 'level-up.user.model', value: AliasingUser::class);

        $this->aliasUser = AliasingUser::query()->create([
            'name' => 'Alias McUser',
            'email' => 'alias@example.test',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
    });

test('host-defined challenges() does not interfere with trait flows', function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [['type' => 'points_earned', 'amount' => 50]],
        'rewards' => [['type' => 'points', 'amount' => 10]],
    ]);

    $this->aliasUser->enrollInChallenge(challenge: $challenge);

    $this->assertDatabaseHas('challenge_user', [
        'user_id' => $this->aliasUser->id,
        'challenge_id' => $challenge->id,
    ]);

    expect($this->aliasUser->packageChallenges()->count())->toBe(1);
    expect($this->aliasUser->activeChallenges()->count())->toBe(1);
    expect($this->aliasUser->completedChallenges()->count())->toBe(0);
    expect($this->aliasUser->getChallengeProgress($challenge))->toBeArray();

    $this->aliasUser->unenrollFromChallenge(challenge: $challenge);

    $this->assertDatabaseMissing('challenge_user', [
        'user_id' => $this->aliasUser->id,
        'challenge_id' => $challenge->id,
    ]);
});

test('host-defined streaks() does not interfere with trait flows', function (): void {
    $activity = Activity::query()->create([
        'name' => 'aliased-activity',
        'description' => 'streak-aliasing-test',
    ]);

    $this->aliasUser->recordStreak(activity: $activity);

    expect($this->aliasUser->getCurrentStreakCount(activity: $activity))->toBe(1);
    expect($this->aliasUser->hasStreakToday(activity: $activity))->toBeTrue();
    expect($this->aliasUser->packageStreaks()->count())->toBe(1);

    $this->aliasUser->freezeStreak(activity: $activity, days: 2);

    expect($this->aliasUser->isStreakFrozen(activity: $activity))->toBeTrue();

    $this->aliasUser->unFreezeStreak(activity: $activity);

    expect($this->aliasUser->isStreakFrozen(activity: $activity))->toBeFalse();
});

test('host-defined experience() does not interfere with trait flows', function (): void {
    $experience = $this->aliasUser->addPoints(amount: 30);

    expect($experience->experience_points)->toBe(30);
    expect($this->aliasUser->getPoints())->toBe(30);
    expect($this->aliasUser->getLevel())->toBe(1);

    $this->aliasUser->addPoints(amount: 80);

    expect($this->aliasUser->getPoints())->toBe(110);

    $this->aliasUser->deductPoints(amount: 10);

    expect($this->aliasUser->getPoints())->toBe(100);

    $this->aliasUser->setPoints(amount: 250);

    expect($this->aliasUser->getPoints())->toBe(250);

    $this->aliasUser->levelUp(to: 2);

    expect($this->aliasUser->getLevel())->toBe(2);

    expect($this->aliasUser->packageExperience()->exists())->toBeTrue();
});
