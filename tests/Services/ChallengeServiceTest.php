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
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->enrollInChallenge(challenge: $challenge);

    Event::fake(eventsToFake: [ChallengeCompleted::class]);

    $this->user->addPoints(amount: 20);

    Event::assertDispatched(event: ChallengeCompleted::class, callback: fn (ChallengeCompleted $event): bool => $event->challenge->is($challenge) && $event->user->is($this->user));
});

test(description: 'points reward is dispatched on completion', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'points', 'amount' => 50],
        ],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->enrollInChallenge(challenge: $challenge);
    $this->user->addPoints(amount: 20);

    expect($this->user->fresh()->getPoints())->toBe(expected: 75);
});

test(description: 'achievement reward is dispatched on completion', closure: function (): void {
    $achievement = Achievement::factory()->create();
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'achievement', 'achievement_id' => $achievement->id],
        ],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->enrollInChallenge(challenge: $challenge);
    $this->user->addPoints(amount: 20);

    $this->assertDatabaseHas(table: 'achievement_user', data: [
        'user_id' => $this->user->id,
        'achievement_id' => $achievement->id,
    ]);
});

test(description: 'achievement not found for reward is silently skipped', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'achievement', 'achievement_id' => 9999],
        ],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->enrollInChallenge(challenge: $challenge);
    $this->user->addPoints(amount: 20);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 'multiple rewards in one challenge', closure: function (): void {
    $achievement = Achievement::factory()->create();
    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'points', 'amount' => 25],
            ['type' => 'achievement', 'achievement_id' => $achievement->id],
        ],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->enrollInChallenge(challenge: $challenge);
    $this->user->addPoints(amount: 20);

    expect($this->user->fresh()->getPoints())->toBe(expected: 50);

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

    $this->user->addPoints(amount: 5);

    Event::fake(eventsToFake: [ChallengeCompleted::class]);

    $this->user->addPoints(amount: 15);

    Event::assertDispatched(event: ChallengeCompleted::class);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = $pivot->progress;

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

    $this->user->addPoints(amount: 5);
    $this->user->addPoints(amount: 15);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = $pivot->progress;

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

    resolve(ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['streak_count', 'custom']);

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

    resolve(ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['custom']);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 'custom with missing class throws on creation', closure: function (): void {
    expect(fn () => Challenge::factory()->create([
        'conditions' => [
            ['type' => 'custom', 'class' => 'App\\NonExistent\\ClassName'],
        ],
        'rewards' => [],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: 'must exist and implement ChallengeCondition');
});

test(description: 'custom with non-implementing class throws on creation', closure: function (): void {
    expect(fn () => Challenge::factory()->create([
        'conditions' => [
            ['type' => 'custom', 'class' => NotACondition::class],
        ],
        'rewards' => [],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: 'must exist and implement ChallengeCondition');
});

test(description: 'unknown condition type throws on creation', closure: function (): void {
    expect(fn () => Challenge::factory()->create([
        'conditions' => [
            ['type' => 'nonexistent_type'],
        ],
        'rewards' => [],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "Invalid condition type 'nonexistent_type'");
});

test(description: 'method_exists guard returns false when user missing trait', closure: function (): void {
    $minimalUser = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $table = 'users';

        protected $guarded = [];
    };

    $minimalUser->fill(['name' => 'Test', 'email' => 'test@test.com', 'password' => 'pass'])->save();

    $service = resolve(ChallengeService::class);

    $reflection = new ReflectionMethod($service, 'checkPointsEarned');
    $result = $reflection->invoke($service, $minimalUser, ['amount' => 10], []);

    expect($result)->toBeFalse();
});

test(description: 'mixed condition types in one challenge', closure: function (): void {
    $achievement = Achievement::factory()->create();

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
            ['type' => 'achievement_earned', 'achievement_id' => $achievement->id],
        ],
        'rewards' => [],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->enrollInChallenge(challenge: $challenge);

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

    $this->user->addPoints(amount: 10);

    $this->assertDatabaseHas(table: 'challenge_user', data: [
        'user_id' => $this->user->id,
        'challenge_id' => $challenge->id,
    ]);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);

    $this->user->addPoints(amount: 60);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
    expect($this->user->fresh()->getPoints())->toBe(expected: 95);
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

    $this->user->addPoints(amount: 5);
    $this->user->addPoints(amount: 15);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 2);
});

test(description: 'E2E: repeatable with baseline — complete, reset, earn more, complete again', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->repeatable()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
        ],
        'rewards' => [],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->addPoints(amount: 60);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = $pivot->progress;
    expect($pivot->completed_at)->toBeNull();
    expect($progress[0]['baseline'])->toBe(expected: 65);

    $this->user->addPoints(amount: 30);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = $pivot->progress;
    expect($progress[0]['completed'])->toBeFalse();

    $this->user->addPoints(amount: 30);

    $pivot = $challenge->users()->where('user_id', $this->user->id)->first()->pivot;
    $progress = $pivot->progress;
    expect($progress[0]['baseline'])->toBe(expected: 125);
});

test(description: 'auto-enroll with existing points uses current baseline, not zero', closure: function (): void {
    $this->user->addPoints(amount: 100);

    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
        ],
        'rewards' => [],
    ]);

    $this->user->addPoints(amount: 10);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);

    $this->user->addPoints(amount: 20);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);

    $this->user->addPoints(amount: 40);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 're-entrancy guard blocks per-user, not globally', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [
            ['type' => 'points', 'amount' => 50],
        ],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->addPoints(amount: 20);

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
    expect($this->user->fresh()->getPoints())->toBe(expected: 75);
});

test(description: 'validation rejects invalid condition type on create', closure: function (): void {
    expect(fn () => Challenge::factory()->create([
        'conditions' => [['type' => 'banana']],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "Invalid condition type 'banana'");
});

test(description: 'validation rejects missing required keys on condition', closure: function (): void {
    expect(fn () => Challenge::factory()->create([
        'conditions' => [['type' => 'points_earned']],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "missing required key 'amount'");
});

test(description: 'validation rejects invalid reward type on create', closure: function (): void {
    expect(fn () => Challenge::factory()->create([
        'conditions' => [['type' => 'points_earned', 'amount' => 10]],
        'rewards' => [['type' => 'gold_coins']],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "Invalid reward type 'gold_coins'");
});

test(description: 'validation rejects missing required keys on reward', closure: function (): void {
    expect(fn () => Challenge::factory()->create([
        'conditions' => [['type' => 'points_earned', 'amount' => 10]],
        'rewards' => [['type' => 'points']],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "missing required key 'amount'");
});

test(description: 'evaluation is skipped when challenges are disabled', closure: function (): void {
    config()->set(key: 'level-up.challenges.enabled', value: false);

    Challenge::factory()->autoEnroll()->create();

    resolve(name: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['points_earned']);

    expect($this->user->challenges()->count())->toBe(expected: 0);
});

test(description: 'progress baselines start at zero when not using the current baseline', closure: function (): void {
    $challenge = Challenge::factory()->create();

    $this->user->addPoints(amount: 50);

    $progress = resolve(name: ChallengeService::class)->initializeProgress(user: $this->user, challenge: $challenge, useCurrentBaseline: false);

    expect($progress[0]['baseline'])->toBe(expected: 0);
});

test(description: 'auto-enrollment tolerates a concurrent enrollment race', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->create();

    $service = new class extends ChallengeService
    {
        public function initializeProgress(Illuminate\Database\Eloquent\Model $user, Challenge $challenge, bool $useCurrentBaseline = true): array
        {
            $table = Illuminate\Support\Facades\DB::table(config('level-up.tables.challenge_user'));

            if ($table->where('challenge_id', $challenge->id)->doesntExist()) {
                $table->insert([
                    config(key: 'level-up.user.foreign_key') => $user->id,
                    'challenge_id' => $challenge->id,
                    'progress' => json_encode(value: []),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return parent::initializeProgress(user: $user, challenge: $challenge, useCurrentBaseline: $useCurrentBaseline);
        }
    };

    $service->evaluateForUser(user: $this->user, conditionTypes: ['points_earned']);

    expect($this->user->challenges()->count())->toBe(expected: 1);
});

test(description: 'evaluation skips a challenge whose enrollment disappears mid-run', closure: function (): void {
    $challenge = Challenge::factory()->create();

    $this->user->enrollInChallenge(challenge: $challenge);

    $service = new class extends ChallengeService
    {
        protected function preloadConditionData(Illuminate\Database\Eloquent\Model $user, Illuminate\Support\Collection $challenges): array
        {
            Illuminate\Support\Facades\DB::table(config('level-up.tables.challenge_user'))->delete();

            return parent::preloadConditionData(user: $user, challenges: $challenges);
        }
    };

    $service->evaluateForUser(user: $this->user, conditionTypes: ['points_earned']);

    expect($this->user->completedChallenges()->count())->toBe(expected: 0);
});

test(description: 'evaluation skips challenges without conditions', closure: function (): void {
    $challenge = Challenge::factory()->create(['conditions' => []]);

    $this->user->enrollInChallenge(challenge: $challenge);

    $service = new class extends ChallengeService
    {
        protected function hasMatchingCondition(Challenge $challenge, array $conditionTypes): bool
        {
            return true;
        }
    };

    $service->evaluateForUser(user: $this->user, conditionTypes: ['points_earned']);

    expect($this->user->completedChallenges()->count())->toBe(expected: 0);
});

test(description: 'condition checks fail gracefully for users without the experience traits', closure: function (): void {
    $bareUser = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $table = 'users';

        protected $guarded = [];
    };

    $bareUser->fill(attributes: ['name' => 'Bare User', 'email' => 'bare@example.test', 'password' => 'secret'])->save();

    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
            ['type' => 'level_reached', 'level' => 2],
            ['type' => 'streak_count', 'activity' => 'login', 'count' => 3],
            ['type' => 'tier_reached', 'tier' => 'Gold'],
        ],
    ]);

    resolve(name: ChallengeService::class)->evaluateForUser(user: $bareUser, conditionTypes: ['points_earned']);

    expect($challenge->users()->count())->toBe(expected: 1)
        ->and($challenge->users()->whereNotNull(config('level-up.tables.challenge_user').'.completed_at')->count())->toBe(expected: 0);
});

test(description: 'reward dispatch reports users missing reward methods and unknown reward types', closure: function (): void {
    $bareUser = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $table = 'users';

        protected $guarded = [];
    };

    $bareUser->fill(attributes: ['name' => 'Bare Winner', 'email' => 'bare-winner@example.test', 'password' => 'secret'])->save();

    $achievement = Achievement::factory()->create();

    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [['type' => 'custom', 'class' => AlwaysTrueCondition::class]],
        'rewards' => [
            ['type' => 'points', 'amount' => 10],
            ['type' => 'achievement', 'achievement_id' => $achievement->id],
        ],
    ]);

    Illuminate\Support\Facades\DB::table('challenges')->where('id', $challenge->id)->update([
        'rewards' => json_encode(value: [
            ['type' => 'points', 'amount' => 10],
            ['type' => 'achievement', 'achievement_id' => $achievement->id],
            ['type' => 'unknown-reward'],
        ]),
    ]);

    resolve(name: ChallengeService::class)->evaluateForUser(user: $bareUser, conditionTypes: ['custom']);

    expect($challenge->users()->whereNotNull(config('level-up.tables.challenge_user').'.completed_at')->count())->toBe(expected: 1);
});

test(description: 'a points reward with a non-positive amount is reported and skipped', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [['type' => 'custom', 'class' => AlwaysTrueCondition::class]],
        'rewards' => [['type' => 'points', 'amount' => 0]],
    ]);

    $this->user->addPoints(amount: 5);
    $this->user->enrollInChallenge(challenge: $challenge);

    resolve(name: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['custom']);

    expect($this->user->completedChallenges()->count())->toBe(expected: 1)
        ->and($this->user->getPoints())->toBe(expected: 5);
});

test(description: 'an achievement reward is skipped when the user already has it', closure: function (): void {
    $achievement = Achievement::factory()->create();

    $this->user->grantAchievement(achievement: $achievement);

    $challenge = Challenge::factory()->create([
        'conditions' => [['type' => 'custom', 'class' => AlwaysTrueCondition::class]],
        'rewards' => [['type' => 'achievement', 'achievement_id' => $achievement->id]],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    resolve(name: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['custom']);

    expect($this->user->allAchievements()->count())->toBe(expected: 1);
});

test(description: 'completion is skipped when another process already completed the challenge', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [['type' => 'custom', 'class' => LevelUp\Experience\Tests\Fixtures\Challenges\CompletesPivotCondition::class]],
        'rewards' => [['type' => 'points', 'amount' => 50]],
    ]);

    $this->user->addPoints(amount: 1);
    $this->user->enrollInChallenge(challenge: $challenge);

    Event::fake(eventsToFake: [ChallengeCompleted::class]);

    resolve(name: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['custom']);

    Event::assertNotDispatched(event: ChallengeCompleted::class);

    expect($this->user->getPoints())->toBe(expected: 1);
});

test(description: 'a missing custom condition class is reported and fails the check', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [['type' => 'custom', 'class' => AlwaysTrueCondition::class]],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    Illuminate\Support\Facades\DB::table('challenges')->where('id', $challenge->id)->update([
        'conditions' => json_encode(value: [['type' => 'custom', 'class' => 'App\NonExistent\ClassName']]),
    ]);

    resolve(name: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['custom']);

    expect($this->user->completedChallenges()->count())->toBe(expected: 0);
});

test(description: 'a custom condition class that does not implement the contract fails the check', closure: function (): void {
    $challenge = Challenge::factory()->create([
        'conditions' => [['type' => 'custom', 'class' => AlwaysTrueCondition::class]],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    Illuminate\Support\Facades\DB::table('challenges')->where('id', $challenge->id)->update([
        'conditions' => json_encode(value: [['type' => 'custom', 'class' => NotACondition::class]]),
    ]);

    resolve(name: ChallengeService::class)->evaluateForUser(user: $this->user, conditionTypes: ['custom']);

    expect($this->user->completedChallenges()->count())->toBe(expected: 0);
});
