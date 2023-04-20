<?php

use LevelUp\Experience\Events\PointsDecreasedEvent;
use LevelUp\Experience\Events\PointsIncreasedEvent;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\Level;
use LevelUp\Experience\Tests\Fixtures\User;

beforeEach(function () {
    /**
     * Always create a new user instance before each test.
     */
    $this->user = new User();

    $this->user->fill(attributes: [
        'name' => 'Chris Mellor',
        'email' => 'chris@mellor.pizza',
        'password' => bcrypt(value: 'password'),
        'email_verified_at' => now(),
    ])->save();

    /**
     * Always create a new level instance before each test.
     */
    (new Level())->fill([
        'level' => 1,
        'next_level_experience' => 100,
    ])->save();
});

test(description: 'giving points to a User without an experience Model, creates a new experience Model', closure: function () {
    // an Experience Model doesn't exist for the User, so this should create one.
    $this->user->addPoints(amount: 10);

    expect($this->user->experience->experience_points)->toBe(expected: 10)
        ->and($this->user->experience)->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 10,
    ]);
});

test(description: 'giving points to a User with an experience Model, updates the experience Model', closure: function () {
    Event::fake();

    // this creates the experience Model
    $this->user->addPoints(amount: 10);
    // so now it will increment the points, instead of creating a new experience Model
    $this->user->addPoints(amount: 10);

    Event::assertDispatched(event: PointsIncreasedEvent::class);

    expect($this->user->experience->experience_points)->toBe(expected: 20)
        ->and($this->user->experience)->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 20,
    ]);
});

it(description: 'can deduct points from a User', closure: function (): void {
    Event::fake();

    $this->user->addPoints(amount: 10);

    $this->user->deductPoints(amount: 5);

    Event::assertDispatched(event: PointsDecreasedEvent::class);

    expect(value: $this->user->experience->experience_points)->toBe(expected: 5)
        ->and($this->user->experience)->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 5,
    ]);
});

it(description: 'can set points to a User', closure: function (): void {
    $this->user->addPoints(amount: 10);

    $this->user->setPoints(amount: 5);

    expect(value: $this->user->experience->experience_points)->toBe(expected: 5)
        ->and($this->user->experience)->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 5,
    ]);
});

it(description: "can retrieve the Users' points", closure: function (): void {
    $this->user->addPoints(amount: 10);

    expect(value: $this->user->getPoints())->toBe(expected: 10);
});
