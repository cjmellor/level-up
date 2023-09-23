<?php

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Listeners\PointsDecreasedListener;

uses()->group('experience');

beforeEach(closure: fn () => config()->set(key: 'level-up.multiplier.enabled', value: false));

test(description: 'the Event is dispatched when points are decreased', closure: function () {
    Event::fakeFor(function () {
        $this->user->addPoints(amount: 100);
        $this->user->deductPoints(amount: 50, reason: 'test');

        Event::assertDispatched(event: PointsDecreased::class, callback: function ($event): bool {
            return $event->pointsDecreasedBy === 50
                && $event->reason === 'test';
        });
        Event::assertListening(expectedEvent: PointsDecreased::class, expectedListener: PointsDecreasedListener::class);
    });
});

test(description: 'the Event Listener runs and logs the audit', closure: function () {
    config()->set(key: 'level-up.audit.enabled', value: true);
    config()->set(key: 'level-up.multiplier.enabled', value: false);

    $this->user->addPoints(amount: 100);
    $this->user->deductPoints(amount: 50, reason: 'test');

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'points' => 50,
        'type' => 'remove',
        'reason' => 'test',
    ]);
});

test(description: 'deducting points does not create an audit record when disabled', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: false);

    $this->user->addPoints(amount: 20);
    $this->user->deductPoints(amount: 10);

    expect($this->user)
        ->experienceHistory()
        ->count()
        ->toBe(expected: 0);
});

test(description: 'deducting points does not create an audit record when the amount is 0', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 20);
    $this->user->deductPoints(amount: 0);

    expect($this->user)
        ->experienceHistory()
        ->count()
        ->toBe(expected: 1);
});
