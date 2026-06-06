<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use LevelUp\Experience\Events\LeaderboardRankChanged;
use LevelUp\Experience\Models\Challenge;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Tests\Fixtures\User;

uses()->group('challenges', 'leaderboard');

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
    config()->set(key: 'level-up.challenges.enabled', value: true);
    config()->set(key: 'level-up.user.model', value: User::class);
});

test(description: 'rank change to within the target completes the challenge on the snapshot run', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp'],
    ]);

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 1],
        ],
        'rewards' => [],
    ]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    $rival = tap(User::newFactory()->create())->addPoints(100);
    $this->user->addPoints(amount: 50);
    $this->user->enrollInChallenge(challenge: $challenge);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    $this->user->addPoints(amount: 100);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1)
        ->and($rival->fresh()->challenges)->toHaveCount(count: 0);
});

test(description: 'rank change that stays worse than the target does not complete the challenge', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp'],
    ]);

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 1],
        ],
        'rewards' => [],
    ]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(200);
    $this->user->addPoints(amount: 100);
    $this->user->enrollInChallenge(challenge: $challenge);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    $this->user->addPoints(amount: 150);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    expect($this->user->fresh()->activeChallenges)->toHaveCount(count: 1)
        ->and($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'entering the tracked depth at or above the target completes the challenge', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp', 'track_top' => 2],
    ]);

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 2],
        ],
        'rewards' => [],
    ]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(200);
    $this->user->addPoints(amount: 100);
    $this->user->enrollInChallenge(challenge: $challenge);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    $this->user->addPoints(amount: 150);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1);
});

test(description: 'rank events from another board do not satisfy a condition keyed to a different board', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp'],
        'level-board' => ['metric' => 'level'],
    ]);

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'level-board', 'rank' => 1],
        ],
        'rewards' => [],
    ]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    $rival = User::newFactory()->create();
    Experience::query()->create(attributes: ['user_id' => $rival->id, 'level_id' => 2, 'experience_points' => 150]);
    Experience::query()->create(attributes: ['user_id' => $this->user->id, 'level_id' => 1, 'experience_points' => 100]);
    $this->user->enrollInChallenge(challenge: $challenge);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    Experience::query()->where(column: 'user_id', operator: '=', value: $this->user->id)->update(['experience_points' => 300]);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    expect($this->user->fresh()->activeChallenges)->toHaveCount(count: 1)
        ->and($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'condition stays unmet when the named board has never been snapshotted', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp'],
    ]);

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 1],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    event(new LeaderboardRankChanged(user: $this->user, board: 'xp-board', from: 2, to: 1));

    expect($this->user->fresh()->activeChallenges)->toHaveCount(count: 1)
        ->and($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'a user missing from the latest snapshot run does not meet the condition in a mixed challenge', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp', 'track_top' => 1],
    ]);

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 50],
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 1],
        ],
        'rewards' => [],
    ]);

    tap(User::newFactory()->create())->addPoints(500);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->user->enrollInChallenge(challenge: $challenge);
    $this->user->addPoints(amount: 60);

    expect($this->user->fresh()->getChallengeProgress(challenge: $challenge))->toBe([
        ['type' => 'points_earned', 'completed' => true, 'baseline' => 0],
        ['type' => 'leaderboard_rank', 'completed' => false],
    ])->and($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'rank events auto-enroll users into auto_enroll leaderboard challenges', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp'],
    ]);

    $challenge = Challenge::factory()->autoEnroll()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 1],
        ],
        'rewards' => [],
    ]);

    $this->travelTo(Date::parse(time: '2026-06-01 06:00:00'));

    tap(User::newFactory()->create())->addPoints(100);
    $this->user->addPoints(amount: 50);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    $this->travelTo(Date::parse(time: '2026-06-02 06:00:00'));
    $this->user->addPoints(amount: 100);

    $this->artisan(command: 'level-up:snapshot-boards')->assertSuccessful();

    expect($this->user->fresh()->completedChallenges)->toHaveCount(count: 1)
        ->and($this->user->fresh()->completedChallenges->first()->is($challenge))->toBeTrue();
});

test(description: 'rank events do not evaluate challenges without leaderboard_rank conditions', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp'],
    ]);

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'points_earned', 'amount' => 10],
        ],
        'rewards' => [],
    ]);

    $this->user->enrollInChallenge(challenge: $challenge);

    Experience::query()->create(attributes: ['user_id' => $this->user->id, 'level_id' => 1, 'experience_points' => 100]);

    event(new LeaderboardRankChanged(user: $this->user, board: 'xp-board', from: 2, to: 1));

    expect($this->user->fresh()->activeChallenges)->toHaveCount(count: 1)
        ->and($this->user->fresh()->completedChallenges)->toHaveCount(count: 0);
});

test(description: 'validation rejects a board that is not declared in config', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp'],
    ]);

    expect(fn (): Challenge => Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'ghost-board', 'rank' => 1],
        ],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "board 'ghost-board' is not declared in level-up.leaderboard.boards");
});

test(description: 'validation rejects a non-string board name', closure: function (): void {
    expect(fn (): Challenge => Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 123, 'rank' => 1],
        ],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "'board' must be a board name string");
});

test(description: 'validation rejects a rank that is not a positive integer', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp'],
    ]);

    expect(fn (): Challenge => Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 0],
        ],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "'rank' must be a positive integer");
});

test(description: 'validation rejects a rank deeper than the board default tracked depth of 100', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp'],
    ]);

    expect(fn (): Challenge => Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 150],
        ],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "rank 150 is deeper than board 'xp-board' tracked depth (100)");
});

test(description: 'validation rejects a rank deeper than a per-board track_top override', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp', 'track_top' => 10],
    ]);

    expect(fn (): Challenge => Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 11],
        ],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "rank 11 is deeper than board 'xp-board' tracked depth (10)");
});

test(description: 'validation accepts a rank within a raised track_top', closure: function (): void {
    config()->set(key: 'level-up.leaderboard.boards', value: [
        'xp-board' => ['metric' => 'xp', 'track_top' => 150],
    ]);

    $challenge = Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank', 'board' => 'xp-board', 'rank' => 120],
        ],
    ]);

    expect($challenge->exists)->toBeTrue();
});

test(description: 'validation rejects a leaderboard_rank condition missing its required keys', closure: function (): void {
    expect(fn (): Challenge => Challenge::factory()->create([
        'conditions' => [
            ['type' => 'leaderboard_rank'],
        ],
    ]))->toThrow(exception: InvalidArgumentException::class, exceptionMessage: "missing required key 'board'");
});
