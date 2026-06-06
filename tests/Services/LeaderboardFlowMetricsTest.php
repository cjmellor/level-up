<?php

declare(strict_types=1);

uses()->group('leaderboard');

use LevelUp\Experience\Enums\Period;
use LevelUp\Experience\Exceptions\MetricDisabledException;
use LevelUp\Experience\Facades\Leaderboard;
use LevelUp\Experience\Metrics\AchievementMetric;
use LevelUp\Experience\Metrics\ChallengeMetric;
use LevelUp\Experience\Models\Achievement;
use LevelUp\Experience\Models\Challenge;
use LevelUp\Experience\Support\LeaderboardEntry;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'ranks users by the number of achievements they have earned', closure: function (): void {
    $collector = User::newFactory()->create();
    $collector->grantAchievement(Achievement::factory()->create());
    $collector->grantAchievement(Achievement::factory()->create());

    $starter = User::newFactory()->create();
    $starter->grantAchievement(Achievement::factory()->create());

    $entries = Leaderboard::by(metric: 'achievements')->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())->toBe([2, 1])
        ->and($entries->first()->user->id)->toEqual($collector->id)
        ->and($entries->last()->user->id)->toEqual($starter->id);
});

it(description: 'treats users with no earned achievements as absent rather than ranking them at zero', closure: function (): void {
    $earner = User::newFactory()->create();
    $earner->grantAchievement(Achievement::factory()->create());

    $entries = Leaderboard::by(metric: 'achievements')->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($earner->id)
        ->and(Leaderboard::by(metric: 'achievements')->rankOf(user: $this->user))->toBeNull();
});

it(description: 'counts secret achievements towards the score without revealing which were earned', closure: function (): void {
    $secretCollector = User::newFactory()->create();
    $secretCollector->grantAchievement(Achievement::factory()->secret()->create());
    $secretCollector->grantAchievement(Achievement::factory()->secret()->create());

    $openEarner = User::newFactory()->create();
    $openEarner->grantAchievement(Achievement::factory()->create());

    $entries = Leaderboard::by(metric: 'achievements')->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())->toBe([2, 1])
        ->and($entries->first()->user->id)->toEqual($secretCollector->id);
});

it(description: 'windows the achievements board to achievements earned within the period', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-03 12:00:00'));
    $pastEarner = User::newFactory()->create();
    $pastEarner->grantAchievement(Achievement::factory()->create());
    $pastEarner->grantAchievement(Achievement::factory()->create());

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 12:00:00'));
    $todayEarner = User::newFactory()->create();
    $todayEarner->grantAchievement(Achievement::factory()->create());

    $entries = Leaderboard::by(metric: 'achievements')->period(period: Period::Day)->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($todayEarner->id)
        ->and($entries->first()->score)->toBe(expected: 1)
        ->and(Leaderboard::by(metric: 'achievements')->period(period: Period::Day)->rankOf(user: $pastEarner))->toBeNull();
});

it(description: 'ranks users by the number of challenges they have completed', closure: function (): void {
    $finisher = User::newFactory()->create();
    Challenge::factory()->count(count: 2)->create()->each(
        callback: fn (Challenge $challenge) => $challenge->users()->attach($finisher->id, ['completed_at' => now()]),
    );

    $runnerUp = User::newFactory()->create();
    Challenge::factory()->create()->users()->attach($runnerUp->id, ['completed_at' => now()]);

    $entries = Leaderboard::by(metric: 'challenges')->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())->toBe([2, 1])
        ->and($entries->first()->user->id)->toEqual($finisher->id)
        ->and($entries->last()->user->id)->toEqual($runnerUp->id);
});

it(description: 'ignores enrolled-but-incomplete challenges and omits users with no completions', closure: function (): void {
    $finisher = User::newFactory()->create();
    Challenge::factory()->create()->users()->attach($finisher->id, ['completed_at' => now()]);
    Challenge::factory()->create()->users()->attach($finisher->id);

    $enrolledOnly = User::newFactory()->create();
    Challenge::factory()->create()->users()->attach($enrolledOnly->id);

    $entries = Leaderboard::by(metric: 'challenges')->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($finisher->id)
        ->and($entries->first()->score)->toBe(expected: 1)
        ->and(Leaderboard::by(metric: 'challenges')->rankOf(user: $enrolledOnly))->toBeNull();
});

it(description: 'throws when generating a challenges board while the challenges system is disabled', closure: function (): void {
    config(['level-up.challenges.enabled' => false]);

    Leaderboard::by(metric: 'challenges')->generate();
})->throws(exception: MetricDisabledException::class, exceptionMessage: 'challenges');

it(description: 'windows the challenges board on when each challenge was completed, not when it was started', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 12:00:00'));

    $pastFinisher = User::newFactory()->create();
    Challenge::factory()->create()->users()->attach($pastFinisher->id, [
        'completed_at' => Illuminate\Support\Facades\Date::parse(time: '2026-06-04 23:59:59'),
    ]);

    $lateBloomer = User::newFactory()->create();
    Challenge::factory()->startsAt(date: Illuminate\Support\Facades\Date::parse(time: '2026-06-01 00:00:00'))->create()
        ->users()->attach($lateBloomer->id, [
            'created_at' => Illuminate\Support\Facades\Date::parse(time: '2026-06-01 09:00:00'),
            'completed_at' => Illuminate\Support\Facades\Date::parse(time: '2026-06-05 09:00:00'),
        ]);

    $entries = Leaderboard::by(metric: 'challenges')->period(period: Period::Day)->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($lateBloomer->id)
        ->and($entries->first()->score)->toBe(expected: 1)
        ->and(Leaderboard::by(metric: 'challenges')->period(period: Period::Day)->rankOf(user: $pastFinisher))->toBeNull();
});

it(description: 'shares a rank between users tied on the same achievement count', closure: function (): void {
    $leader = User::newFactory()->create();
    $leader->grantAchievement(Achievement::factory()->create());
    $leader->grantAchievement(Achievement::factory()->create());

    $tiedOne = User::newFactory()->create();
    $tiedOne->grantAchievement(Achievement::factory()->create());

    $tiedTwo = User::newFactory()->create();
    $tiedTwo->grantAchievement(Achievement::factory()->create());

    $entries = Leaderboard::by(metric: 'achievements')->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())->toBe([1, 2, 2])
        ->and(Leaderboard::by(metric: 'achievements')->rankOf(user: $tiedTwo))->toBe(expected: 2);
});

it(description: 'exposes a stable key and label on the achievement metric', closure: function (): void {
    $metric = new AchievementMetric();

    expect($metric->key())->toBe(expected: 'achievements')
        ->and($metric->label())->toBe(expected: 'Achievements')
        ->and($metric->enabled())->toBeTrue();
});

it(description: 'exposes a stable key and label on the challenge metric, mirroring the feature toggle', closure: function (): void {
    $metric = new ChallengeMetric();

    expect($metric->key())->toBe(expected: 'challenges')
        ->and($metric->label())->toBe(expected: 'Challenges')
        ->and($metric->enabled())->toBeTrue();

    config(['level-up.challenges.enabled' => false]);

    expect($metric->enabled())->toBeFalse();
});
