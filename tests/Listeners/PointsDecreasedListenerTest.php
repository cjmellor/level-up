<?php

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Listeners\PointsDecreasedListener;

beforeEach(closure: function (): void {
    config()->set(key: 'level-up.multiplier.enabled', value: false);
});

uses()->group('experience');

test(description: 'the Event and Listener run when points are deducted from a User Model', closure: function (): void {
    Event::fakeFor(callable: function (): void {
        // this creates the experience Model
        $this->user->addPoints(amount: 20);
        // so now it will decrement the points
        $this->user->deductPoints(amount: 10);

        Event::assertDispatched(event: PointsDecreased::class);
        Event::assertListening(expectedEvent: PointsDecreased::class, expectedListener: PointsDecreasedListener::class);
    });
});

test(description: 'deducting points creates an audit record', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 20);
    $this->user->deductPoints(amount: 10);

    expect($this->user)
        ->experienceHistory()->count()->toBe(expected: 2);

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'user_id' => $this->user->id,
        'points' => -10,
        'levelled_up' => false,
        'level_to' => null,
        'type' => AuditType::Remove->value,
        'reason' => null,
    ]);
});

test(description: 'deducting points does not create an audit record when disabled', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: false);

    $this->user->addPoints(amount: 20);
    $this->user->deductPoints(amount: 10);

    expect($this->user)
        ->experienceHistory()->count()->toBe(expected: 0);
});

test(description: 'deducting points does not create an audit record when the amount is 0', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 20);
    $this->user->deductPoints(amount: 0);

    expect($this->user)
        ->experienceHistory()->count()->toBe(expected: 1);
});

test(description: 'deducting points creates an audit record with a reason', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 20);
    $this->user->deductPoints(amount: 10, reason: 'test');

    expect($this->user)
        ->experienceHistory()->count()->toBe(expected: 2);

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'user_id' => $this->user->id,
        'points' => -10,
        'levelled_up' => false,
        'level_to' => null,
        'type' => AuditType::Remove->value,
        'reason' => 'test',
    ]);
});
