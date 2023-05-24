<?php

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Listeners\PointsIncreasedListener;

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
});

uses()->group('experience');

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

test(description: 'adding points creates an audit record', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 10);

    expect($this->user)
        ->experienceHistory()->count()->toBe(expected: 1);

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'user_id' => $this->user->id,
        'type' => AuditType::Add->value,
        'points' => 10,
    ]);
});

test(description: 'when a User levels up, a record is stored in the audit', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 100);

    expect($this->user)
        ->experienceHistory()->count()->toBe(expected: 2);

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'user_id' => $this->user->id,
        'points' => 100,
        'levelled_up' => true,
        'level_to' => 2,
        'type' => AuditType::LevelUp->value,
    ]);
});
