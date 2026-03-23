<?php

declare(strict_types=1);

use LevelUp\Experience\Models\Challenge;

uses()->group('challenges');

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
    config()->set(key: 'level-up.user.model', value: \LevelUp\Experience\Tests\Fixtures\User::class);

    $this->challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 100],
        ],
        'rewards' => [
            ['type' => 'points', 'amount' => 50],
        ],
    ]);
});

test(description: 'a User can enroll in a challenge', closure: function (): void {
    $this->user->enrollInChallenge(challenge: $this->challenge);

    $this->assertDatabaseHas(table: 'challenge_user', data: [
        'user_id' => $this->user->id,
        'challenge_id' => $this->challenge->id,
    ]);

    expect($this->user->challenges)->toHaveCount(count: 1);
});

test(description: 'a User cannot enroll in the same challenge twice', closure: function (): void {
    $this->user->enrollInChallenge(challenge: $this->challenge);

    expect(fn () => $this->user->enrollInChallenge(challenge: $this->challenge))
        ->toThrow(exception: Exception::class, exceptionMessage: 'User is already enrolled in this challenge.');
});

test(description: 'active challenges returns only incomplete', closure: function (): void {
    $this->user->enrollInChallenge(challenge: $this->challenge);

    expect($this->user->activeChallenges)->toHaveCount(count: 1);
    expect($this->user->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'completed challenges returns only completed', closure: function (): void {
    $this->user->enrollInChallenge(challenge: $this->challenge);

    $this->challenge->users()->updateExistingPivot($this->user->id, attributes: [
        'completed_at' => now(),
    ]);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
    expect($this->user->fresh()->activeChallenges)->toHaveCount(count: 0);
});

test(description: 'getChallengeProgress returns progress array', closure: function (): void {
    $this->user->enrollInChallenge(challenge: $this->challenge);

    $progress = $this->user->getChallengeProgress(challenge: $this->challenge);

    expect($progress)->toBeArray()
        ->and($progress[0]['type'])->toBe(expected: 'points_earned')
        ->and($progress[0]['completed'])->toBeFalse();
});

test(description: 'getChallengeProgress returns null for unenrolled challenge', closure: function (): void {
    expect($this->user->getChallengeProgress(challenge: $this->challenge))->toBeNull();
});
