<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\Level;

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
});

uses()->group('experience');

test(description: 'giving points to a User without an experience Model, creates a new experience Model', closure: function (): void {
    // an Experience Model doesn't exist for the User, so this should create one.
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

    // this creates the experience Model
    $this->user->addPoints(amount: 10);
    // so now it will increment the points, instead of creating a new experience Model
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

test(description: 'levels are associated on point increments', closure: function () {
    $this->user->addPoints(amount: 10);

    expect($this->user)->level_id->toBe(expected: 1);

    $this->user->addPoints(amount: 100);

    expect($this->user)->level_id->toBe(expected: 2)
        ->and($this->user)->getLevel()->toBe(expected: 2);
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
})->throws(exception: \Exception::class, exceptionMessage: 'User has no experience record.');

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

test(description: 'points can be multiplied', closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: true);
    config()->set(key: 'level-up.multiplier.path', value: 'tests/Fixtures/Multipliers');
    config()->set(key: 'level-up.multiplier.namespace', value: 'LevelUp\\Experience\\Tests\\Fixtures\\Multipliers\\');

    Carbon::setTestNow(Carbon::create(month: 12));

    $this->user->addPoints(amount: 10);

    expect(value: $this->user->experience)
        ->experience_points->toBe(expected: 50)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 50,
    ]);
});

test('a User can see how many more points are needed until they can level up', closure: function (): void {
    $this->user->addPoints(amount: 100);

    expect($this->user)
        ->nextLevelAt()
        ->toBe(expected: 150);

    $this->user->setPoints(0); // reset points
    $this->user->addPoints(amount: 249);

    expect($this->user)
        ->nextLevelAt(showAsPercentage: true)
        ->toBe(expected: 99);
});

it(description: 'returns zero when User has hit Level cap and tries to see how many points until next level', closure: function () {
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

test('A multiplier can use data that was passed through to it', closure: function () {
    config()->set(key: 'level-up.multiplier.enabled', value: true);
    config()->set(key: 'level-up.multiplier.path', value: 'tests/Fixtures/Multipliers');
    config()->set(key: 'level-up.multiplier.namespace', value: 'LevelUp\\Experience\\Tests\\Fixtures\\Multipliers\\');

    $this->user
        ->withMultiplierData([
            'event_id' => 2,
        ])
        ->addPoints(amount: 10);

    expect($this->user->experience)
        ->experience_points->toBe(expected: 50)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 50,
    ]);
});

test(description: 'an anonymous function can be used as a multiplier condition', closure: function () {
    $this->user
        ->withMultiplierData(fn () => true)
        ->addPoints(amount: 10, multiplier: 2);

    expect($this->user)
        ->experience->experience_points->toBe(expected: 20)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);

    /*
     * Check the opposite of the above -- if condition is false, multiplier should not be applied
     * */
    $this->user
        ->withMultiplierData(fn () => false)
        ->addPoints(amount: 10, multiplier: 2);

    expect($this->user)
        ->experience->experience_points->toBe(expected: 30)
        ->and($this->user)->experience->toBeInstanceOf(class: Experience::class);
});

test(description: 'Add default level if not applied before trying to add points', closure: function () {
    // In this scenario, no Level Model should be applied to the User, so the default level should be applied
    Level::truncate();

    // The Levels table should be empty
    expect(Level::count())->toBe(expected: 0);

    $this->user->addPoints(amount: 10);

    // The Levels table should now have 1 record
    expect(Level::count())->toBe(expected: 1);

    // Assert the data in the Levels table is correct
    $this->assertDatabaseHas(table: 'levels', data: [
        'id' => 1,
        'level' => 1,
        'next_level_experience' => null,
    ]);
});

it(description: 'throws an error when points added exceed the last levels experience requirement')
    ->defer(fn () => $this->user->addPoints(amount: 1000))
    ->throws(exception: \Exception::class);

test(description: 'the level is correct when adding more points than available on initial experience gain', closure: function () {
    // Levels have been added, up to Level 5, needing to reach 600 points to get there
    // User is initially given 400 points, so should directly go to Level 4
    $this->user->addPoints(amount: 10);
    expect($this->user)->level_id->toBe(expected: 1);
    $this->user->setPoints(amount: 0);

    $this->user->addPoints(100);
    expect($this->user)->level_id->toBe(expected: 2);
    $this->user->setPoints(amount: 0);

    $this->user->addPoints(250);
    expect($this->user)->level_id->toBe(expected: 3);
    $this->user->setPoints(amount: 0);

    $this->user->addPoints(400);
    expect($this->user)->level_id->toBe(expected: 4);
    $this->user->setPoints(amount: 0);
});

it(description: 'dispatches an event after points have been added for the very first time', closure: function () {
    Event::fake();

    $this->user->addPoints(amount: 250);

    Event::assertDispatched(event: UserLevelledUp::class);
});
