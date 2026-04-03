<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\MultiplierApplied;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\Level;
use LevelUp\Experience\Models\Multiplier;

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
});

uses()->group('experience');

test(description: 'giving points to a User without an experience Model, creates a new experience Model', closure: function (): void {
    $this->user->addPoints(amount: 10);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 10)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 10,
    ]);
});

test(description: 'giving points to a User with an experience Model, updates the experience Model', closure: function (): void {
    Event::fake();

    $this->user->addPoints(amount: 10);
    $this->user->addPoints(amount: 10);

    Event::assertDispatched(event: PointsIncreased::class);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 20)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 20,
    ]);
});

test(description: 'levels are associated on point increments', closure: function (): void {
    $this->user->addPoints(amount: 10);

    expect($this->user->experience)->level_id->toBe(expected: 1);

    $this->user->addPoints(amount: 100);

    expect($this->user)->getLevel()->toBe(expected: 2);
});

it(description: 'can deduct points from a User', closure: function (): void {
    Event::fake();

    $this->user->addPoints(amount: 10);

    $this->user->deductPoints(amount: 5);

    Event::assertDispatched(event: PointsDecreased::class);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 5)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 5,
    ]);
});

it(description: 'can set points to a User', closure: function (): void {
    $this->user->addPoints(amount: 10);

    $this->user->setPoints(amount: 5);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 5)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 5,
    ]);
});

test(description: 'it throws an error if points cannot be set', closure: function (): void {
    $this->user->setPoints(amount: 5);
})->throws(exception: Exception::class, exceptionMessage: 'User has no experience record.');

it(description: "can retrieve the Users' points", closure: function (): void {
    $this->user->addPoints(amount: 10);

    expect(value: $this->user)
        ->getPoints()
        ->toBe(expected: 10);
});

test(description: 'when using a multiplier, times the points by it', closure: function (): void {
    $this->user->addPoints(amount: 10, multiplier: 2);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 20)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 20,
    ]);
});

test(description: 'DB multipliers are applied automatically when active', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);

    Multiplier::query()->create([
        'name' => 'Double XP',
        'multiplier' => 2,
        'is_active' => true,
    ]);

    $this->user->addPoints(amount: 10);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 20)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 20,
    ]);
});

test(description: 'inactive DB multipliers are not applied', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);

    Multiplier::query()->create([
        'name' => 'Inactive Bonus',
        'multiplier' => 5,
        'is_active' => false,
    ]);

    $this->user->addPoints(amount: 10);

    expect($this->user->experience)->experience_points->toBe(expected: 10);
});

test(description: 'time-based multiplier applies within window', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);

    Multiplier::query()->create([
        'name' => 'Weekend Bonus',
        'multiplier' => 3,
        'is_active' => true,
        'starts_at' => now()->subHour(),
        'expires_at' => now()->addHour(),
    ]);

    $this->user->addPoints(amount: 10);

    expect($this->user->experience)->experience_points->toBe(expected: 30);
});

test(description: 'expired multiplier is not applied', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);

    Multiplier::query()->create([
        'name' => 'Expired Bonus',
        'multiplier' => 3,
        'is_active' => true,
        'starts_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
    ]);

    $this->user->addPoints(amount: 10);

    expect($this->user->experience)->experience_points->toBe(expected: 10);
});

test(description: 'scheduled multiplier is not applied before start time', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);

    Multiplier::query()->create([
        'name' => 'Future Bonus',
        'multiplier' => 3,
        'is_active' => true,
        'starts_at' => now()->addDay(),
        'expires_at' => now()->addDays(2),
    ]);

    $this->user->addPoints(amount: 10);

    expect($this->user->experience)->experience_points->toBe(expected: 10);
});

test(description: 'compound stacking multiplies multipliers together', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);
    config()->set(key: 'level-up.multiplier.stack_strategy', value: 'compound');

    Multiplier::query()->create(['name' => 'A', 'multiplier' => 2, 'is_active' => true]);
    Multiplier::query()->create(['name' => 'B', 'multiplier' => 5, 'is_active' => true]);

    $this->user->addPoints(amount: 10);

    expect($this->user->experience)->experience_points->toBe(expected: 100);
});

test(description: 'additive stacking sums multiplier values', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);
    config()->set(key: 'level-up.multiplier.stack_strategy', value: 'additive');

    Multiplier::query()->create(['name' => 'A', 'multiplier' => 2, 'is_active' => true]);
    Multiplier::query()->create(['name' => 'B', 'multiplier' => 5, 'is_active' => true]);

    $this->user->addPoints(amount: 10);

    expect($this->user->experience)->experience_points->toBe(expected: 70);
});

test(description: 'highest stacking uses only the largest multiplier', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);
    config()->set(key: 'level-up.multiplier.stack_strategy', value: 'highest');

    Multiplier::query()->create(['name' => 'A', 'multiplier' => 2, 'is_active' => true]);
    Multiplier::query()->create(['name' => 'B', 'multiplier' => 5, 'is_active' => true]);

    $this->user->addPoints(amount: 10);

    expect($this->user->experience)->experience_points->toBe(expected: 50);
});

test(description: 'inline multiplier stacks with DB multipliers', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);
    config()->set(key: 'level-up.multiplier.stack_strategy', value: 'compound');

    Multiplier::query()->create(['name' => 'DB Bonus', 'multiplier' => 3, 'is_active' => true]);

    $this->user->addPoints(amount: 10, multiplier: 2);

    expect($this->user->experience)->experience_points->toBe(expected: 60);
});

test(description: 'float inline multiplier works', closure: function (): void {
    $this->user->addPoints(amount: 10, multiplier: 1.5);

    expect($this->user->experience)->experience_points->toBe(expected: 15);
});

test(description: 'MultiplierApplied event fires when multipliers are applied', closure: function (): void {
    Event::fake([MultiplierApplied::class]);
    config()->set(key: 'level-up.multiplier.enabled', value: true);

    Multiplier::query()->create(['name' => 'Bonus', 'multiplier' => 2, 'is_active' => true]);

    $this->user->addPoints(amount: 10);

    Event::assertDispatched(MultiplierApplied::class, fn (MultiplierApplied $event): bool => $event->originalAmount === 10
        && $event->finalAmount === 20
        && $event->multipliers->count() === 1);
});

test(description: 'no multipliers applied when multiplier feature is disabled', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);

    Multiplier::query()->create(['name' => 'Bonus', 'multiplier' => 5, 'is_active' => true]);

    $this->user->addPoints(amount: 10);

    expect($this->user->experience)->experience_points->toBe(expected: 10);
});

test('a User can see how many more points are needed until they can level up', closure: function (): void {
    $this->user->addPoints(amount: 100);

    expect($this->user)
        ->nextLevelAt()
        ->toBe(expected: 150);

    $this->user->setPoints(0);
    $this->user->addPoints(amount: 249);

    expect($this->user)
        ->nextLevelAt(showAsPercentage: true)
        ->toBe(expected: 99);
});

it(description: 'returns zero when User has hit Level cap and tries to see how many points until next level', closure: function (): void {
    config()->set(key: 'level-up.level_cap.enabled', value: true);
    config()->set(key: 'level-up.level_cap.level', value: 3);
    config()->set(key: 'level-up.level_cap.points_continue', value: false);

    $this->user->addPoints(amount: 100);
    $this->user->addPoints(amount: 150);

    expect($this->user)
        ->nextLevelAt()
        ->toBe(expected: 0);
});

test(description: 'when a User hits the next level threshold, their level will increase to the correct level', closure: function (): void {
    Event::fake([UserLevelledUp::class]);

    $this->user->addPoints(amount: 1);
    $this->user->addPoints(amount: 99);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 100)
        ->and($this->user->getLevel())->toBe(expected: 2);

    Event::assertDispatched(event: UserLevelledUp::class);

    $this->user->addPoints(amount: 150);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 250)
        ->and($this->user->getLevel())->toBe(expected: 3);
});

test(description: 'when the level cap is enabled, and a User hits that level cap, they will not level up', closure: function (): void {
    config()->set(key: 'level-up.level_cap.enabled', value: true);
    config()->set(key: 'level-up.level_cap.level', value: 2);

    $this->user->addPoints(amount: 1);
    $this->user->addPoints(amount: 149);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 150)
        ->and($this->user)->getLevel()->toBe(expected: 2);

    $this->user->addPoints(amount: 150);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 300)
        ->and($this->user)->getLevel()->toBe(expected: 2);
});

test(description: 'when the level cap is enabled, and a User hits that level cap, they will not level up, but they can continue to earn points', closure: function (): void {
    config()->set(key: 'level-up.level_cap.enabled', value: true);
    config()->set(key: 'level-up.level_cap.level', value: 2);

    $this->user->addPoints(amount: 1);
    $this->user->addPoints(amount: 149);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 150)
        ->and($this->user)->getLevel()->toBe(expected: 2);

    $this->user->addPoints(amount: 150);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 300)
        ->and($this->user)->getLevel()->toBe(expected: 2);
});

test('when the level cap is enabled, and a User hits that level cap, they will not level up, and points will freeze', function (): void {
    config()->set(key: 'level-up.level_cap.enabled', value: true);
    config()->set(key: 'level-up.level_cap.level', value: 2);
    config()->set(key: 'level-up.level_cap.points_continue', value: false);

    $this->user->addPoints(amount: 1);
    $this->user->addPoints(amount: 249);

    expect($this->user->experience)
        ->experience_points->toBe(expected: 250);

    $this->user->addPoints(amount: 100);

    expect($this->user->experience)
        ->experience_points->toBe(expected: 250);
});

test('a Users level is restored if the level cap is re-enabled and points continue to increment', function (): void {
    config()->set(key: 'level-up.level_cap.enabled', value: false);

    $this->user->addPoints(amount: 1);
    $this->user->addPoints(amount: 99);

    expect($this->user->experience)
        ->experience_points->toBe(expected: 100)
        ->and($this->user)->getLevel()->toBe(expected: 2);

    config()->set(key: 'level-up.level_cap.enabled', value: true);
    config()->set(key: 'level-up.level_cap.level', value: 2);

    $this->user->addPoints(amount: 200);

    expect($this->user->experience)
        ->experience_points->toBe(expected: 300)
        ->and($this->user)->getLevel()->toBe(expected: 2);

    config()->set(key: 'level-up.level_cap.enabled', value: false);

    $this->user->addPoints(amount: 100);

    expect($this->user->experience)
        ->experience_points->toBe(expected: 400)
        ->and($this->user)->getLevel()->toBe(expected: 4);
});

test(description: 'Add default level if not applied before trying to add points', closure: function (): void {
    Level::query()->truncate();

    expect(Level::query()->count())->toBe(expected: 0);

    $this->user->addPoints(amount: 10);

    expect(Level::query()->count())->toBe(expected: 1);

    $this->assertDatabaseHas(table: 'levels', data: [
        'id' => 1,
        'level' => 1,
        'next_level_experience' => null,
    ]);
});

it(description: 'throws an error when points added exceed the last levels experience requirement')
    ->defer(fn () => $this->user->addPoints(amount: 1000))
    ->throws(exception: Exception::class);

test(description: 'the level is correct when adding more points than available on initial experience gain', closure: function (): void {
    $this->user->addPoints(amount: 10);
    expect($this->user->experience)->level_id->toBe(expected: 1);
    $this->user->setPoints(amount: 0);

    $this->user->addPoints(100);
    expect($this->user->getLevel())->toBe(expected: 2);
    $this->user->setPoints(amount: 0);

    $this->user->addPoints(250);
    expect($this->user->getLevel())->toBe(expected: 3);
    $this->user->setPoints(amount: 0);

    $this->user->addPoints(400);
    expect($this->user->getLevel())->toBe(expected: 4);
    $this->user->setPoints(amount: 0);
});

it(description: 'dispatches an event after points have been added for the very first time', closure: function (): void {
    Event::fake();

    $this->user->addPoints(amount: 250);

    Event::assertDispatched(event: UserLevelledUp::class);
});

it('returns 0 when level is not set', function (): void {
    $this->user->experience = null;

    expect($this->user->getLevel())->toBe(0);
});

it('returns 0 when experience points are not set', function (): void {
    $this->user->experience = null;

    expect($this->user->getPoints())->toBe(0);
});

test(description: 'events fire in chronological order when creating new experience record', closure: function (): void {
    $eventsDispatched = [];

    Event::listen(PointsIncreased::class, function (PointsIncreased $event) use (&$eventsDispatched): void {
        $eventsDispatched[] = [
            'type' => 'PointsIncreased',
            'timestamp' => microtime(true),
            'points' => $event->pointsAdded,
            'total_points' => $event->totalPoints,
        ];
    });

    Event::listen(UserLevelledUp::class, function (UserLevelledUp $event) use (&$eventsDispatched): void {
        $eventsDispatched[] = [
            'type' => 'UserLevelledUp',
            'timestamp' => microtime(true),
            'level' => $event->level,
        ];
    });

    $this->user->addPoints(amount: 250);

    expect($eventsDispatched)->toHaveCount(4)
        ->and($eventsDispatched[0]['type'])->toBe('PointsIncreased')
        ->and($eventsDispatched[0]['points'])->toBe(250)
        ->and($eventsDispatched[0]['total_points'])->toBe(250)
        ->and($eventsDispatched[1]['type'])->toBe('UserLevelledUp')
        ->and($eventsDispatched[1]['level'])->toBe(1)
        ->and($eventsDispatched[2]['type'])->toBe('UserLevelledUp')
        ->and($eventsDispatched[2]['level'])->toBe(2)
        ->and($eventsDispatched[3]['type'])->toBe('UserLevelledUp')
        ->and($eventsDispatched[3]['level'])->toBe(3)
        ->and($eventsDispatched[0]['timestamp'])->toBeLessThanOrEqual($eventsDispatched[1]['timestamp'])
        ->and($eventsDispatched[1]['timestamp'])->toBeLessThanOrEqual($eventsDispatched[2]['timestamp'])
        ->and($eventsDispatched[2]['timestamp'])->toBeLessThanOrEqual($eventsDispatched[3]['timestamp']);
});

test(description: 'PointsIncreased event fires before UserLevelledUp when leveling up from starting level', closure: function (): void {
    $eventsOrder = [];

    Event::listen(PointsIncreased::class, function () use (&$eventsOrder): void {
        $eventsOrder[] = 'PointsIncreased';
    });

    Event::listen(UserLevelledUp::class, function () use (&$eventsOrder): void {
        $eventsOrder[] = 'UserLevelledUp';
    });

    $this->user->addPoints(amount: 100);

    expect($eventsOrder)->toBe(['PointsIncreased', 'UserLevelledUp', 'UserLevelledUp'])
        ->and($this->user)->getLevel()->toBe(2);
});

test(description: 'no UserLevelledUp events fire when staying at starting level but PointsIncreased still fires', closure: function (): void {
    $eventsOrder = [];

    Event::listen(PointsIncreased::class, function () use (&$eventsOrder): void {
        $eventsOrder[] = 'PointsIncreased';
    });

    Event::listen(UserLevelledUp::class, function () use (&$eventsOrder): void {
        $eventsOrder[] = 'UserLevelledUp';
    });

    $this->user->addPoints(amount: 50);

    expect($eventsOrder)->toBe(['PointsIncreased'])
        ->and($this->user)->getLevel()->toBe(1)
        ->and($this->user)->getPoints()->toBe(50);
});

test(description: 'levelUp throws InvalidArgumentException when level does not exist', closure: function (): void {
    $this->user->addPoints(amount: 10);

    $this->user->levelUp(to: 999);
})->throws(InvalidArgumentException::class, 'Level 999 does not exist.');

test(description: 'levelUp associates the user with the correct level', closure: function (): void {
    $this->user->addPoints(amount: 10);

    $this->user->levelUp(to: 3);

    expect($this->user->getLevel())->toBe(3);
});

test(description: 'levelUp does nothing when level cap is reached', closure: function (): void {
    config()->set('level-up.level_cap.enabled', true);
    config()->set('level-up.level_cap.level', 3);

    $this->user->addPoints(amount: 250);

    expect($this->user->getLevel())->toBe(3);

    $this->user->levelUp(to: 4);

    expect($this->user->getLevel())->toBe(3);
});

test(description: 'deductPoints throws when user has no experience record', closure: function (): void {
    $this->user->deductPoints(amount: 50);
})->throws(Exception::class, 'User has no experience record.');

test(description: 'nextLevelAt returns 0 when current level is missing from database', closure: function (): void {
    $this->user->addPoints(amount: 10);

    Level::query()->where('level', $this->user->getLevel())->delete();

    expect($this->user->nextLevelAt())->toBe(0);
});

test(description: 'unknown stacking strategy throws InvalidArgumentException', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);
    config()->set(key: 'level-up.multiplier.stack_strategy', value: 'invalid_strategy');

    Multiplier::query()->create(['name' => 'A', 'multiplier' => 2, 'is_active' => true]);

    $this->user->addPoints(amount: 10);
})->throws(InvalidArgumentException::class, 'Unknown multiplier stack strategy: invalid_strategy');
