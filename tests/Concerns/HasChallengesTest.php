<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Models\Challenge;

uses()->group('challenges');

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
    config()->set(key: 'level-up.user.model', value: LevelUp\Experience\Tests\Fixtures\User::class);

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

test(description: 'a User can re-enroll in a completed repeatable challenge', closure: function (): void {
    $challenge = Challenge::factory()->repeatable()->create([
        'conditions' => [['type' => 'points_earned', 'amount' => 100]],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    $challenge->users()->updateExistingPivot($this->user->id, [
        'completed_at' => now(),
    ]);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);

    $this->user->enrollInChallenge(challenge: $challenge);

    expect($this->user->fresh()->activeChallenges)->toHaveCount(count: 1);
    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'a User cannot re-enroll in a completed non-repeatable challenge', closure: function (): void {
    $this->user->enrollInChallenge(challenge: $this->challenge);

    $this->challenge->users()->updateExistingPivot($this->user->id, [
        'completed_at' => now(),
    ]);

    expect(fn () => $this->user->enrollInChallenge(challenge: $this->challenge))
        ->toThrow(exception: Exception::class, exceptionMessage: 'This challenge is completed and not repeatable.');
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

test(description: 'enrolling in an expired challenge throws', closure: function (): void {
    $challenge = Challenge::factory()->expiresAt(now()->subDay())->create([
        'conditions' => [['type' => 'points_earned', 'amount' => 10]],
    ]);

    expect(fn () => $this->user->enrollInChallenge(challenge: $challenge))
        ->toThrow(exception: Exception::class, exceptionMessage: 'This challenge has expired.');
});

test(description: 'enrolling in a future challenge throws', closure: function (): void {
    $challenge = Challenge::factory()->startsAt(now()->addDay())->create([
        'conditions' => [['type' => 'points_earned', 'amount' => 10]],
    ]);

    expect(fn () => $this->user->enrollInChallenge(challenge: $challenge))
        ->toThrow(exception: Exception::class, exceptionMessage: 'This challenge has not started yet.');
});

test(description: 'a User can unenroll from an active challenge', closure: function (): void {
    $this->user->enrollInChallenge(challenge: $this->challenge);

    expect($this->user->challenges)->toHaveCount(count: 1);

    $this->user->unenrollFromChallenge(challenge: $this->challenge);

    expect($this->user->fresh()->challenges)->toHaveCount(count: 0);
});

test(description: 'unenrolling from a challenge you are not enrolled in throws', closure: function (): void {
    expect(fn () => $this->user->unenrollFromChallenge(challenge: $this->challenge))
        ->toThrow(exception: Exception::class, exceptionMessage: 'User is not enrolled in this challenge.');
});

test(description: 'unenrolling from a completed challenge throws', closure: function (): void {
    $this->user->enrollInChallenge(challenge: $this->challenge);

    $this->challenge->users()->updateExistingPivot($this->user->id, attributes: [
        'completed_at' => now(),
    ]);

    expect(fn () => $this->user->unenrollFromChallenge(challenge: $this->challenge))
        ->toThrow(exception: Exception::class, exceptionMessage: 'Cannot unenroll from a completed challenge.');
});

test(description: 'getChallengeCompletionPercentage returns correct percentage', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 100],
            ['type' => 'level_reached', 'level' => 5],
        ],
        'rewards' => [],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->enrollInChallenge(challenge: $challenge);

    expect($this->user->getChallengeCompletionPercentage(challenge: $challenge))->toBe(expected: 0.0);

    $this->user->addPoints(amount: 110);

    expect($this->user->getChallengeCompletionPercentage(challenge: $challenge))->toBe(expected: 50.0);
});

test(description: 'getChallengeCompletionPercentage returns null for unenrolled challenge', closure: function (): void {
    expect($this->user->getChallengeCompletionPercentage(challenge: $this->challenge))->toBeNull();
});

test(description: 'ChallengeEnrolled event fires on enrollment', closure: function (): void {
    Event::fake(eventsToFake: [LevelUp\Experience\Events\ChallengeEnrolled::class]);

    $this->user->enrollInChallenge(challenge: $this->challenge);

    Event::assertDispatched(LevelUp\Experience\Events\ChallengeEnrolled::class);
});

test(description: 'ChallengeUnenrolled event fires on unenrollment', closure: function (): void {
    $this->user->enrollInChallenge(challenge: $this->challenge);

    Event::fake(eventsToFake: [LevelUp\Experience\Events\ChallengeUnenrolled::class]);

    $this->user->unenrollFromChallenge(challenge: $this->challenge);

    Event::assertDispatched(LevelUp\Experience\Events\ChallengeUnenrolled::class);
});
