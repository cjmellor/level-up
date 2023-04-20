<?php

use LevelUp\Experience\Events\PointsIncreasedEvent;
use LevelUp\Experience\Listeners\PointsIncreasedListener;
use LevelUp\Experience\Models\Experience;

it(description: 'the Event and Listener run when points are added to a User Model', closure: function (): void {
    Event::fakeFor(callable: function (): void {
        // this creates the experience Model
        $this->user->addPoints(amount: 10);
        // so now it will increment the points, instead of creating a new experience Model
        $this->user->addPoints(amount: 10);

        Event::assertDispatched(event: PointsIncreasedEvent::class);
        Event::assertListening(expectedEvent: PointsIncreasedEvent::class, expectedListener: PointsIncreasedListener::class);
    });

    // TODO: check against what actions the listener performs

    expect(value: $this->user->experience->experience_points)->toBe(expected: 20)
        ->and($this->user->experience)->toBeInstanceOf(class: Experience::class);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
        'experience_points' => 20,
    ]);
});
