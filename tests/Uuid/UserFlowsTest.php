<?php

declare(strict_types=1);

use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Challenge;

test('experience flows work with a UUID-keyed user', function (): void {
    $experience = $this->user->addPoints(amount: 30);

    expect($experience->experience_points)->toBe(30);
    expect($this->user->getPoints())->toBe(30);
    expect($this->user->getLevel())->toBe(1);

    $this->user->addPoints(amount: 80);

    expect($this->user->getPoints())->toBe(110);

    $this->user->deductPoints(amount: 10);

    expect($this->user->getPoints())->toBe(100);

    $this->user->setPoints(amount: 250);

    expect($this->user->getPoints())->toBe(250);

    $this->user->levelUp(to: 2);

    expect($this->user->getLevel())->toBe(2);
});

test('streak flows work with a UUID-keyed user', function (): void {
    $activity = Activity::query()->create([
        'name' => 'uuid-activity',
        'description' => 'uuid streak test',
    ]);

    $this->user->recordStreak(activity: $activity);

    expect($this->user->getCurrentStreakCount(activity: $activity))->toBe(1);
    expect($this->user->hasStreakToday(activity: $activity))->toBeTrue();

    $this->assertDatabaseHas('streaks', [
        'user_id' => $this->user->id,
        'activity_id' => $activity->id,
    ]);

    $this->user->freezeStreak(activity: $activity, days: 2);

    expect($this->user->isStreakFrozen(activity: $activity))->toBeTrue();

    $this->user->unFreezeStreak(activity: $activity);

    expect($this->user->isStreakFrozen(activity: $activity))->toBeFalse();
});

test('challenge flows work with a UUID-keyed user', function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [['type' => 'points_earned', 'amount' => 50]],
        'rewards' => [['type' => 'points', 'amount' => 10]],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    $this->assertDatabaseHas('challenge_user', [
        'user_id' => $this->user->id,
        'challenge_id' => $challenge->id,
    ]);

    expect($this->user->activeChallenges()->count())->toBe(1);
    expect($this->user->completedChallenges()->count())->toBe(0);

    $this->user->unenrollFromChallenge(challenge: $challenge);

    $this->assertDatabaseMissing('challenge_user', [
        'user_id' => $this->user->id,
        'challenge_id' => $challenge->id,
    ]);
});
