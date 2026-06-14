<?php

declare(strict_types=1);

uses()->group('leaderboard');

use Illuminate\Support\Carbon;
use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Enums\Period;
use LevelUp\Experience\Exceptions\MetricNotWindowableException;
use LevelUp\Experience\Exceptions\MetricRequiresAuditingException;
use LevelUp\Experience\Facades\Leaderboard;
use LevelUp\Experience\Support\LeaderboardEntry;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'windows the xp board to points earned within the current day', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-03 12:00:00'));
    tap(User::newFactory()->create())->addPoints(50);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 12:00:00'));
    $todayEarner = tap(User::newFactory()->create())->addPoints(44);

    $entries = Leaderboard::period(period: Period::Day)->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first())->toBeInstanceOf(class: LeaderboardEntry::class)
        ->and($entries->first()->user->id)->toEqual($todayEarner->id)
        ->and($entries->first()->score)->toBe(expected: 44);
});

it(description: 'computes the windowed score as points added minus points removed', closure: function (): void {
    $user = tap(User::newFactory()->create())->addPoints(80);
    $user->deductPoints(30);

    $entries = Leaderboard::period(period: Period::Day)->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->score)->toBe(expected: 50);
});

it(description: 'excludes state-change audit rows from the windowed score', closure: function (): void {
    $user = tap(User::newFactory()->create())->addPoints(150);

    expect($user->experienceHistory()->where(column: 'type', operator: '=', value: AuditType::LevelUp->value)->exists())->toBeTrue();

    $user->experienceHistory()->create(attributes: ['points' => 150, 'type' => AuditType::Reset->value]);
    $user->experienceHistory()->create(attributes: ['points' => 150, 'type' => AuditType::TierUp->value]);
    $user->experienceHistory()->create(attributes: ['points' => 150, 'type' => AuditType::TierDown->value]);

    $entries = Leaderboard::period(period: Period::Day)->generate();

    expect($entries->first()->score)->toBe(expected: 150);
});

it(description: 'keeps the all-time board reading experience points without the audit ledger', closure: function (): void {
    config(['level-up.audit.enabled' => false]);

    $user = tap(User::newFactory()->create())->addPoints(120);

    expect($user->experienceHistory()->count())->toBe(expected: 0);

    $entries = Leaderboard::generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->score)->toBe(expected: 120);
});

it(description: 'ignores setPoints overrides on periodic boards while the all-time board sees them', closure: function (): void {
    $earner = tap(User::newFactory()->create())->addPoints(10);
    $overridden = tap(User::newFactory()->create())->addPoints(5);
    $overridden->setPoints(1000);

    $periodic = Leaderboard::period(period: Period::Day)->generate();
    $allTime = Leaderboard::generate();

    expect($periodic->map(fn (LeaderboardEntry $entry): int|float => $entry->score)->toArray())->toBe([10, 5])
        ->and($periodic->first()->user->id)->toEqual($earner->id)
        ->and($allTime->first()->user->id)->toEqual($overridden->id)
        ->and($allTime->first()->score)->toBe(expected: 1000);
});

it(description: 'throws immediately when selecting a period for a non-Windowable metric', closure: function (): void {
    Leaderboard::by(metric: 'level')->period(period: Period::Day);
})->throws(exception: MetricNotWindowableException::class, exceptionMessage: 'level');

it(description: 'throws when generating a periodic board after switching to a non-Windowable metric', closure: function (): void {
    Leaderboard::period(period: Period::Day)->by(metric: 'level')->generate();
})->throws(exception: MetricNotWindowableException::class, exceptionMessage: 'level');

it(description: 'throws a descriptive exception when a periodic board is requested with auditing disabled', closure: function (): void {
    config(['level-up.audit.enabled' => false]);

    tap(User::newFactory()->create())->addPoints(50);

    Leaderboard::period(period: Period::Day)->generate();
})->throws(exception: MetricRequiresAuditingException::class, exceptionMessage: 'level-up.audit.enabled');

it(description: 'windows the board to a custom open-ended range with since', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-01 12:00:00'));
    $earlyEarner = tap(User::newFactory()->create())->addPoints(100);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-04 12:00:00'));
    $recentEarner = tap(User::newFactory()->create())->addPoints(30);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 12:00:00'));
    $earlyEarner->addPoints(20);

    $entries = Leaderboard::since(start: Illuminate\Support\Facades\Date::parse(time: '2026-06-03 00:00:00'))->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int|float => $entry->score)->toArray())->toBe([30, 20])
        ->and($entries->first()->user->id)->toEqual($recentEarner->id)
        ->and($entries->last()->user->id)->toEqual($earlyEarner->id);
});

it(description: 'bounds a custom range with an until timestamp, excluding rows at or after it', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-04 12:00:00'));
    $insideEarner = tap(User::newFactory()->create())->addPoints(30);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 00:00:00'));
    $boundaryEarner = tap(User::newFactory()->create())->addPoints(99);

    $entries = Leaderboard::since(
        start: Illuminate\Support\Facades\Date::parse(time: '2026-06-03 00:00:00'),
        until: Illuminate\Support\Facades\Date::parse(time: '2026-06-05 00:00:00'),
    )->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($insideEarner->id)
        ->and(Leaderboard::since(start: Illuminate\Support\Facades\Date::parse(time: '2026-06-05 00:00:00'))->rankOf(user: $boundaryEarner))->toBe(expected: 1);
});

it(description: 'starts the week on Monday by default, including rows from the boundary onwards', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-05-31 23:59:00'));
    tap(User::newFactory()->create())->addPoints(70);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-01 00:00:00'));
    $mondayEarner = tap(User::newFactory()->create())->addPoints(80);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-03 12:00:00'));

    $entries = Leaderboard::period(period: Period::Week)->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($mondayEarner->id)
        ->and($entries->first()->score)->toBe(expected: 80);
});

it(description: 'respects a configured week start day', closure: function (): void {
    config(['level-up.leaderboard.week_starts_on' => Carbon::SUNDAY]);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-05-30 23:59:00'));
    tap(User::newFactory()->create())->addPoints(70);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-05-31 08:00:00'));
    $sundayEarner = tap(User::newFactory()->create())->addPoints(80);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-03 12:00:00'));

    $entries = Leaderboard::period(period: Period::Week)->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($sundayEarner->id);
});

it(description: 'computes period boundaries in the configured timezone', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 03:00:00', timezone: 'UTC'));
    $lateNightEarner = tap(User::newFactory()->create())->addPoints(60);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 05:00:00', timezone: 'UTC'));
    $morningEarner = tap(User::newFactory()->create())->addPoints(40);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 12:00:00', timezone: 'UTC'));

    $utcBoard = Leaderboard::period(period: Period::Day)->generate();

    expect($utcBoard)->toHaveCount(count: 2);

    config(['level-up.leaderboard.timezone' => 'America/New_York']);

    $newYorkBoard = Leaderboard::period(period: Period::Day)->generate();

    expect($newYorkBoard)->toHaveCount(count: 1)
        ->and($newYorkBoard->first()->user->id)->toEqual($morningEarner->id)
        ->and(Leaderboard::period(period: Period::Day)->rankOf(user: $lateNightEarner))->toBeNull();
});

it(description: 'windows the board to the current month', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-05-31 23:00:00'));
    tap(User::newFactory()->create())->addPoints(70);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-01 10:00:00'));
    $juneEarner = tap(User::newFactory()->create())->addPoints(80);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-15 12:00:00'));

    $entries = Leaderboard::period(period: Period::Month)->generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($juneEarner->id);
});

it(description: 'treats users without qualifying audit rows in the window as absent from the board', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-03 12:00:00'));
    $pastEarner = tap(User::newFactory()->create())->addPoints(500);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 12:00:00'));
    tap(User::newFactory()->create())->addPoints(44);

    expect(Leaderboard::period(period: Period::Day)->rankOf(user: $pastEarner))->toBeNull()
        ->and(Leaderboard::period(period: Period::Day)->around(user: $pastEarner, range: 2))->toBeEmpty()
        ->and(Leaderboard::period(period: Period::Day)->rankOf(user: $this->user))->toBeNull();
});

it(description: 'composes periodic boards with ranks, ties, rankOf and around', closure: function (): void {
    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-03 12:00:00'));
    $allTimeLeader = tap(User::newFactory()->create())->addPoints(900);

    $this->travelTo(Illuminate\Support\Facades\Date::parse(time: '2026-06-05 12:00:00'));
    $tiedOne = tap(User::newFactory()->create())->addPoints(200);
    $tiedTwo = tap(User::newFactory()->create())->addPoints(200);
    $third = tap(User::newFactory()->create())->addPoints(100);
    $allTimeLeader->addPoints(50);

    $entries = Leaderboard::period(period: Period::Day)->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int|float => $entry->score)->toArray())->toBe([200, 200, 100, 50])
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())->toBe([1, 1, 3, 4])
        ->and(Leaderboard::period(period: Period::Day)->rankOf(user: $tiedTwo))->toBe(expected: 1)
        ->and(Leaderboard::period(period: Period::Day)->rankOf(user: $allTimeLeader))->toBe(expected: 4)
        ->and(Leaderboard::rankOf(user: $allTimeLeader))->toBe(expected: 1);

    $around = Leaderboard::period(period: Period::Day)->around(user: $third, range: 1);

    expect($around)->toHaveCount(count: 3)
        ->and($around->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())->toBe([1, 3, 4])
        ->and($around[1]->user->id)->toEqual($third->id);
});
