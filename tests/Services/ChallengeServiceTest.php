<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\ChallengeCompleted;
use LevelUp\Experience\Models\Achievement;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Challenge;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Services\ChallengeService;
use LevelUp\Experience\Tests\Fixtures\Challenges\AlwaysTrueCondition;
use LevelUp\Experience\Tests\Fixtures\Challenges\NotACondition;

use function Pest\Laravel\travel;

uses()->group('challenges');

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
    config()->set(key: 'level-up.challenges.enabled', value: true);
    config()->set(key: 'level-up.user.model', value: LevelUp\Experience\Tests\Fixtures\User::class);
});

test(description: 'challenge completes when all conditions are met', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
        ],
    ]);

    $this->user->addPoints(amount: 10);
    $this->user->enrollInChallenge(challenge: $challenge);
    $this->user->addPoints(amount: 60);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 'challenge stays incomplete when some conditions are unmet', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
            ['type' => 'level_reached', 'level' => 5],
        ],
    ]);

    $this->user->addPoints(amount: 10);
    $this->user->enrollInChallenge(challenge: $challenge);
    $this->user->addPoints(amount: 60);

    expect($this->user->fresh()->activeChallenges)->toHaveCount(count: 1);
    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'ChallengeCompleted event fires on completion', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [],
    ]);

    Event::fake(eventsToFake: [ChallengeCompleted::class]);

    $this->user->addPoints(amount: 20);

    Event::assertDispatched(event: ChallengeCompleted::class, callback: fn (ChallengeCompleted $event): bool => $event->challenge->is($challenge) && $event->user->is($this->user));
});

test(description: 'points reward is dispatched on completion', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'points', 'amount' => 50],
        ],
    ]);

    $this->user->addPoints(amount: 20);

    expect($this->user->fresh()->getPoints())->toBe(expected: 70);
});

test(description: 'achievement reward is dispatched on completion', closure: function (): void {
    $achievement = Achievement::factory()->create();
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'achievement', 'achievement_id' => $achievement->id],
        ],
    ]);

    $this->user->addPoints(amount: 20);

    $this->assertDatabaseHas(table: 'achievement_user', data: [
        'user_id' => $this->user->id,
        'achievement_id' => $achievement->id,
    ]);
});

test(description: 'achievement not found for reward is silently skipped', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'achievement', 'achievement_id' => 9999],
        ],
    ]);

    $this->user->addPoints(amount: 20);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 'multiple rewards in one challenge', closure: function (): void {
    $achievement = Achievement::factory()->create();
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'points', 'amount' => 25],
            ['type' => 'achievement', 'achievement_id' => $achievement->id],
        ],
    ]);

    $this->user->addPoints(amount: 20);

    expect($this->user->fresh()->getPoints())->toBe(expected: 45);

    $this->assertDatabaseHas(table: 'achievement_user', data: [
        'user_id' => $this->user->id,
        'achievement_id' => $achievement->id,
    ]);
});

test(description: 'repeatable challenge resets progress after completion', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->repeatable()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [],
    ]);

    Event::fake(eventsToFake: [ChallengeCompleted::class]);

    $this->user->addPoints(amount: 20);

    Event::assertDispatched(event: ChallengeCompleted::class);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = is_string($pivot->progress) ? json_decode($pivot->progress, true) : $pivot->progress;

    expect($pivot->completed_at)->toBeNull();
    expect($progress[0]['completed'])->toBeFalse();
});

test(description: 'repeatable challenge re-captures baseline on reset', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->repeatable()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [],
    ]);

    $this->user->addPoints(amount: 20);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = is_string($pivot->progress) ? json_decode($pivot->progress, true) : $pivot->progress;

    expect($progress[0]['baseline'])->toBe(expected: 20);
});

test(description: 'expired challenge is not evaluated', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->expiresAt(now()->subDay())->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
    ]);

    $this->user->addPoints(amount: 20);

    $this->assertDatabaseMissing(table: 'challenge_user', data: [
        'user_id' => $this->user->id,
        'challenge_id' => $challenge->id,
    ]);
});

test(description: 'future challenge is not evaluated', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->startsAt(now()->addDay())->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
    ]);

    $this->user->addPoints(amount: 20);

    $this->assertDatabaseMissing(table: 'challenge_user', data: [
        'user_id' => $this->user->id,
        'challenge_id' => $challenge->id,
    ]);
});

test(description: 'feature disabled skips evaluation', closure: function (): void {
    config()->set(key: 'level-up.challenges.enabled', value: false);

    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
    ]);

    $this->user->addPoints(amount: 20);

    $this->assertDatabaseMissing(table: 'challenge_user', data: [
        'user_id' => $this->user->id,
    ]);
});

test(description: 'empty conditions array is skipped', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [],
        'rewards' => [
            ['type' => 'points', 'amount' => 100],
        ],
    ]);

    $this->user->addPoints(amount: 10);

    $pointsAfter = $this->user->fresh()->getPoints();
    expect($pointsAfter)->toBe(expected: 10);
});

test(description: 'points_earned uses baseline delta not total points', closure: function (): void {
    $this->user->addPoints(amount: 100);

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    $this->user->addPoints(amount: 30);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);

    $this->user->addPoints(amount: 30);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 'level_reached checks current level', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'level_reached', 'level' => 2],
        ],
        'rewards' => [],
    ]);

    Event::fake(eventsToFake: [ChallengeCompleted::class]);

    $this->user->addPoints(amount: 100);

    Event::assertDispatched(event: ChallengeCompleted::class);
});

test(description: 'achievement_earned checks user has achievement', closure: function (): void {
    $achievement = Achievement::factory()->create();
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'achievement_earned', 'achievement_id' => $achievement->id],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    $this->user->grantAchievement(achievement: $achievement);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 'streak_count checks current streak for activity', closure: function (): void {
    $activity = Activity::factory()->create(['name' => 'daily-login']);
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'streak_count', 'activity' => 'daily-login', 'count' => 2],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    $this->user->recordStreak(activity: $activity);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);

    travel(1)->day();
    $this->user->recordStreak(activity: $activity);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 'streak_count with unknown activity returns false', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'streak_count', 'activity' => 'nonexistent-activity', 'count' => 1],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    app(abstract: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['streak_count', 'custom']);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'tier_reached checks isAtOrAboveTier', closure: function (): void {
    Tier::add(
        ['name' => 'Bronze', 'experience' => 0],
        ['name' => 'Silver', 'experience' => 100],
    );

    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'tier_reached', 'tier' => 'Silver'],
        ],
        'rewards' => [],
    ]);

    Event::fake(eventsToFake: [ChallengeCompleted::class]);

    $this->user->addPoints(amount: 150);

    Event::assertDispatched(event: ChallengeCompleted::class);
});

test(description: 'custom condition delegates to ChallengeCondition class', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'custom', 'class' => AlwaysTrueCondition::class],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    app(abstract: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['custom']);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 'custom with missing class returns false', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'custom', 'class' => 'App\\NonExistent\\ClassName'],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    app(abstract: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['custom']);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'custom with non-implementing class returns false', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'custom', 'class' => NotACondition::class],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    app(abstract: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['custom']);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'unknown condition type returns false', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'nonexistent_type'],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    app(abstract: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['nonexistent_type']);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'method_exists guard returns false when user missing trait', closure: function (): void {
    $minimalUser = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $table = 'users';

        protected $guarded = [];
    };

    $minimalUser->fill(['name' => 'Test', 'email' => 'test@test.com', 'password' => 'pass'])->save();

    $service = app(abstract: ChallengeService::class);

    $reflection = new ReflectionMethod($service, 'checkPointsEarned');
    $result = $reflection->invoke($service, $minimalUser, ['amount' => 10], []);

    expect($result)->toBeFalse();
});

test(description: 'mixed condition types in one challenge', closure: function (): void {
    $achievement = Achievement::factory()->create();

    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
            ['type' => 'achievement_earned', 'achievement_id' => $achievement->id],
        ],
        'rewards' => [],
    ]);

    Event::fake(eventsToFake: [ChallengeCompleted::class]);

    $this->user->addPoints(amount: 60);

    Event::assertNotDispatched(event: ChallengeCompleted::class);

    Event::fake(eventsToFake: [ChallengeCompleted::class]);

    $this->user->grantAchievement(achievement: $achievement);

    Event::assertDispatched(event: ChallengeCompleted::class);
});

test(description: 'E2E: auto-enroll → addPoints → complete → rewards dispatched', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
        ],
        'rewards' => [
            ['type' => 'points', 'amount' => 25],
        ],
    ]);

    $this->user->addPoints(amount: 60);

    $this->assertDatabaseHas(table: 'challenge_user', data: [
        'user_id' => $this->user->id,
        'challenge_id' => $challenge->id,
    ]);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
    expect($this->user->fresh()->getPoints())->toBe(expected: 85);
});

test(description: 'E2E: reward cascade — challenge A reward completes challenge B', closure: function (): void {
    Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'points', 'amount' => 100],
        ],
    ]);

    Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 90],
        ],
        'rewards' => [],
    ]);

    $this->user->addPoints(amount: 20);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 2);
});

test(description: 'E2E: repeatable with baseline — complete, reset, earn more, complete again', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->repeatable()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
        ],
        'rewards' => [],
    ]);

    $this->user->addPoints(amount: 60);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = is_string($pivot->progress) ? json_decode($pivot->progress, true) : $pivot->progress;
    expect($pivot->completed_at)->toBeNull();
    expect($progress[0]['baseline'])->toBe(expected: 60);

    $this->user->addPoints(amount: 30);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = is_string($pivot->progress) ? json_decode($pivot->progress, true) : $pivot->progress;
    expect($progress[0]['completed'])->toBeFalse();

    $this->user->addPoints(amount: 30);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = is_string($pivot->progress) ? json_decode($pivot->progress, true) : $pivot->progress;
    expect($progress[0]['baseline'])->toBe(expected: 120);
});
