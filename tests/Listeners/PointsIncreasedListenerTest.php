<?php

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Listeners\PointsIncreasedListener;

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
});

test(description: 'the Event and Listener run when points are added to a User Model', closure: function (): void {
    Event::fakeFor(callable: function (): void {
        // this creates the experience Model
        $this->user->addPoints(amount: 10);
        // so now it will increment the points, instead of creating a new experience Model
        $this->user->addPoints(amount: 10);

        Event::assertDispatched(event: PointsIncreased::class);
        Event::assertListening(expectedEvent: PointsIncreased::class, expectedListener: PointsIncreasedListener::class);
    });
});

test(description: 'adding points creates an audit record', closure: function () {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 10);

    expect($this->user)
        ->experienceHistory()->count()->toBe(1);

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'user_id' => $this->user->id,
        'type' => 'add',
        'points' => 10,
    ]);
});
