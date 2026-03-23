<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\AchievementAwarded;
use LevelUp\Experience\Events\ChallengeCompleted;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\StreakIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Events\UserTierUpdated;
use LevelUp\Experience\Listeners\ChallengeProgressListener;
use LevelUp\Experience\Models\Challenge;

uses()->group('challenges', 'listeners');

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
    config()->set(key: 'level-up.challenges.enabled', value: true);
    config()->set(key: 'level-up.user.model', value: \LevelUp\Experience\Tests\Fixtures\User::class);
});

test(description: 'ChallengeProgressListener is registered on PointsIncreased', closure: function (): void {
    Event::fake();
    Event::assertListening(expectedEvent: PointsIncreased::class, expectedListener: ChallengeProgressListener::class);
});

test(description: 'ChallengeProgressListener is registered on AchievementAwarded', closure: function (): void {
    Event::fake();
    Event::assertListening(expectedEvent: AchievementAwarded::class, expectedListener: ChallengeProgressListener::class);
});

test(description: 'ChallengeProgressListener is registered on StreakIncreased', closure: function (): void {
    Event::fake();
    Event::assertListening(expectedEvent: StreakIncreased::class, expectedListener: ChallengeProgressListener::class);
});

test(description: 'ChallengeProgressListener is registered on UserLevelledUp', closure: function (): void {
    Event::fake();
    Event::assertListening(expectedEvent: UserLevelledUp::class, expectedListener: ChallengeProgressListener::class);
});

test(description: 'ChallengeProgressListener is registered on UserTierUpdated', closure: function (): void {
    Event::fake();
    Event::assertListening(expectedEvent: UserTierUpdated::class, expectedListener: ChallengeProgressListener::class);
});

test(description: 'ChallengeProgressListener is NOT registered on ChallengeCompleted', closure: function (): void {
    Event::fake();

    $listening = false;

    try {
        Event::assertListening(expectedEvent: ChallengeCompleted::class, expectedListener: ChallengeProgressListener::class);
        $listening = true;
    } catch (\PHPUnit\Framework\AssertionFailedError) {
        $listening = false;
    }

    expect($listening)->toBeFalse();
});

test(description: 'auto-enroll creates pivot on first relevant event', closure: function (): void {
    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 100],
        ],
    ]);

    $this->user->addPoints(amount: 10);

    $this->assertDatabaseHas(table: 'challenge_user', data: [
        'user_id' => $this->user->id,
        'challenge_id' => $challenge->id,
    ]);
});

test(description: 'auto-enroll does not duplicate on subsequent events', closure: function (): void {
    Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 100],
        ],
    ]);

    $this->user->addPoints(amount: 10);
    $this->user->addPoints(amount: 10);
    $this->user->addPoints(amount: 10);

    expect($this->user->challenges)->toHaveCount(count: 1);
});
