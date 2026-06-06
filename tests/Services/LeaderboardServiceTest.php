<?php

declare(strict_types=1);

uses()->group('leaderboard');

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use LevelUp\Experience\Exceptions\MetricDisabledException;
use LevelUp\Experience\Exceptions\MetricNotFoundException;
use LevelUp\Experience\Facades\Leaderboard;
use LevelUp\Experience\Metrics\ExperienceMetric;
use LevelUp\Experience\Support\LeaderboardEntry;
use LevelUp\Experience\Tests\Fixtures\User;
use LevelUp\Experience\Tests\Fixtures\UserIdMetric;

beforeEach(function (): void {
    config(['level-up.user.model' => User::class]);
    config(['level-up.multiplier.enabled' => false]);
});

it(description: 'generates leaderboard entries with user and score, ordered by score descending', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);
    tap(User::newFactory()->create())->addPoints(198);
    tap(User::newFactory()->create())->addPoints(245);

    $entries = Leaderboard::generate();

    expect($entries)->toHaveCount(count: 4)
        ->and($entries->first())->toBeInstanceOf(class: LeaderboardEntry::class)
        ->and($entries->first()->user)->toBeInstanceOf(class: User::class)
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())
        ->toBe([245, 198, 123, 44]);
});

it(description: 'ranks by an explicitly selected metric key', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);

    $entries = Leaderboard::by(metric: 'xp')->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())
        ->toBe([123, 44]);
});

it(description: 'throws for an unknown metric key', closure: function (): void {
    Leaderboard::by(metric: 'nonsense');
})->throws(exception: MetricNotFoundException::class, exceptionMessage: 'nonsense');

it(description: 'ranks by a custom metric resolved from its class name', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(999);
    tap(User::newFactory()->create())->addPoints(1);

    $entries = Leaderboard::by(metric: UserIdMetric::class)->generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int|float => $entry->score)->toArray())
        ->toBe([3, 2, 1]);
});

it(description: 'ranks by a custom metric instance', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(999);
    tap(User::newFactory()->create())->addPoints(1);

    $entries = Leaderboard::by(metric: new UserIdMetric())->generate();

    expect($entries->first()->score)->toBe(3);
});

it(description: 'uses the configured default metric for a bare generate call', closure: function (): void {
    config([
        'level-up.leaderboard.metrics.user-id' => UserIdMetric::class,
        'level-up.leaderboard.default_metric' => 'user-id',
    ]);

    tap(User::newFactory()->create())->addPoints(999);

    $entries = Leaderboard::generate();

    expect($entries)->toHaveCount(count: 2)
        ->and($entries->first()->score)->toBe(2);
});

it(description: 'throws when generating with a metric whose feature is disabled', closure: function (): void {
    Leaderboard::by(metric: new UserIdMetric(enabled: false))->generate();
})->throws(exception: MetricDisabledException::class, exceptionMessage: 'user-id');

it(description: 'paginates leaderboard entries', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);

    $paginated = Leaderboard::generate(paginate: true);

    expect($paginated)->toBeInstanceOf(class: LengthAwarePaginator::class)
        ->and($paginated->total())->toBe(expected: 2)
        ->and($paginated->items()[0])->toBeInstanceOf(class: LeaderboardEntry::class)
        ->and($paginated->items()[0]->score)->toBe(expected: 123);
});

it(description: 'limits the number of leaderboard entries', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);
    tap(User::newFactory()->create())->addPoints(198);

    $entries = Leaderboard::generate(limit: 2);

    expect($entries)->toHaveCount(count: 2)
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())
        ->toBe([198, 123]);
});

it(description: 'exposes sequential rank numbers on leaderboard entries', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(44);
    tap(User::newFactory()->create())->addPoints(123);
    tap(User::newFactory()->create())->addPoints(198);

    $entries = Leaderboard::generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())
        ->toBe([1, 2, 3]);
});

it(description: 'shares a rank between tied scores and skips the next rank', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(200);
    tap(User::newFactory()->create())->addPoints(200);
    tap(User::newFactory()->create())->addPoints(100);

    $entries = Leaderboard::generate();

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())
        ->toBe([1, 1, 3]);
});

it(description: 'orders tied rows deterministically by user key ascending', closure: function (): void {
    $third = tap(User::newFactory()->create())->addPoints(100);
    $first = tap(User::newFactory()->create())->addPoints(200);
    $second = tap(User::newFactory()->create())->addPoints(200);

    $entries = Leaderboard::generate();

    expect($entries->map(fn (LeaderboardEntry $entry): mixed => $entry->user->id)->toArray())
        ->toBe([$first->id, $second->id, $third->id]);
});

it(description: 'returns the exact rank of a user, sharing ranks between ties', closure: function (): void {
    $tiedOne = tap(User::newFactory()->create())->addPoints(200);
    $tiedTwo = tap(User::newFactory()->create())->addPoints(200);
    $third = tap(User::newFactory()->create())->addPoints(100);

    expect(Leaderboard::rankOf(user: $tiedOne))->toBe(expected: 1)
        ->and(Leaderboard::rankOf(user: $tiedTwo))->toBe(expected: 1)
        ->and(Leaderboard::rankOf(user: $third))->toBe(expected: 3);
});

it(description: 'returns null from rankOf for a user absent from the board', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(100);

    expect(Leaderboard::rankOf(user: $this->user))->toBeNull();
});

it(description: 'returns the rank of a user on an explicitly selected metric', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(999);
    tap(User::newFactory()->create())->addPoints(1);

    expect(Leaderboard::by(metric: UserIdMetric::class)->rankOf(user: $this->user))->toBe(expected: 3);
});

it(description: 'returns the entries around a user with their board-wide ranks', closure: function (): void {
    $users = collect(value: [500, 400, 300, 200, 100])
        ->map(fn (int $points): User => tap(User::newFactory()->create())->addPoints($points));

    $entries = Leaderboard::around(user: $users[2], range: 1);

    expect($entries)->toHaveCount(count: 3)
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->score)->toArray())
        ->toBe([400, 300, 200])
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())
        ->toBe([2, 3, 4])
        ->and($entries[1]->user->id)->toEqual($users[2]->id);
});

it(description: 'clamps the around window at the top of the board', closure: function (): void {
    $leader = tap(User::newFactory()->create())->addPoints(500);
    tap(User::newFactory()->create())->addPoints(400);
    tap(User::newFactory()->create())->addPoints(300);
    tap(User::newFactory()->create())->addPoints(200);

    $entries = Leaderboard::around(user: $leader, range: 2);

    expect($entries)->toHaveCount(count: 3)
        ->and($entries->first()->user->id)->toEqual($leader->id)
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())
        ->toBe([1, 2, 3]);
});

it(description: 'clamps the around window at the bottom of the board', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(500);
    tap(User::newFactory()->create())->addPoints(400);
    tap(User::newFactory()->create())->addPoints(300);
    $last = tap(User::newFactory()->create())->addPoints(200);

    $entries = Leaderboard::around(user: $last, range: 2);

    expect($entries)->toHaveCount(count: 3)
        ->and($entries->last()->user->id)->toEqual($last->id)
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())
        ->toBe([2, 3, 4]);
});

it(description: 'returns an empty collection from around for a user absent from the board', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(100);

    $entries = Leaderboard::around(user: $this->user, range: 2);

    expect($entries)->toBeInstanceOf(class: Illuminate\Support\Collection::class)
        ->and($entries)->toBeEmpty();
});

it(description: 'returns the entries around a user on an explicitly selected metric', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(999);
    tap(User::newFactory()->create())->addPoints(1);

    $entries = Leaderboard::by(metric: UserIdMetric::class)->around(user: $this->user, range: 1);

    expect($entries)->toHaveCount(count: 2)
        ->and($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())
        ->toBe([2, 3])
        ->and($entries->last()->user->id)->toEqual($this->user->id);
});

it(description: 'keeps board-wide ranks when limiting entries', closure: function (): void {
    tap(User::newFactory()->create())->addPoints(200);
    tap(User::newFactory()->create())->addPoints(200);
    tap(User::newFactory()->create())->addPoints(100);

    $entries = Leaderboard::generate(limit: 2);

    expect($entries->map(fn (LeaderboardEntry $entry): int => $entry->rank)->toArray())
        ->toBe([1, 1]);
});

it(description: 'keeps board-wide ranks on later pages of a paginated leaderboard', closure: function (): void {
    foreach (range(start: 1, end: 16) as $points) {
        tap(User::newFactory()->create())->addPoints($points * 10);
    }

    Illuminate\Pagination\Paginator::currentPageResolver(resolver: fn (): int => 2);

    $paginated = Leaderboard::generate(paginate: true);

    expect($paginated->total())->toBe(expected: 16)
        ->and($paginated->items())->toHaveCount(count: 1)
        ->and($paginated->items()[0]->rank)->toBe(expected: 16);
});

it(description: 'exposes a stable key and label on the experience metric', closure: function (): void {
    $metric = new ExperienceMetric();

    expect($metric->key())->toBe(expected: 'xp')
        ->and($metric->label())->toBe(expected: 'Experience')
        ->and($metric->enabled())->toBeTrue();
});

it(description: 'only includes users with experience points', closure: function (): void {
    tap(User::newFactory()->create());
    $userWithPoints = tap(User::newFactory()->create())->addPoints(44);

    $entries = Leaderboard::generate();

    expect($entries)->toHaveCount(count: 1)
        ->and($entries->first()->user->id)->toEqual($userWithPoints->id);
});
