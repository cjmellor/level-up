<?php

declare(strict_types=1);

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
        $this->user->addPoints(amount: 10);
        $this->user->addPoints(amount: 10);

        Event::assertDispatched(event: PointsIncreased::class);
        Event::assertListening(expectedEvent: PointsIncreased::class, expectedListener: PointsIncreasedListener::class);
    });
});

test(description: 'the level_id in Experience table defaults to 1 on Model creation', closure: function (): void {
    $this->user->addPoints(1);

    expect($this->user->experience->level_id)->toBe(expected: 1);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 1,
    ]);
});

test(description: 'adding points creates an audit record', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 10);

    expect($this->user)
        ->experienceHistory()->count()->toBe(expected: 1);

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'user_id' => $this->user->id,
        'points' => 10,
        'levelled_up' => false,
        'level_to' => null,
        'type' => AuditType::Add->value,
        'reason' => null,
    ]);
});

test(description: 'when a User levels up, a record is stored in the audit', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 100);

    expect($this->user)
        ->experienceHistory()->count()->toBe(expected: 3);

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'user_id' => $this->user->id,
        'points' => 100,
        'levelled_up' => true,
        'level_to' => 2,
        'type' => AuditType::LevelUp->value,
        'reason' => null,
    ]);
});

test(description: 'user levels are correct', closure: function (): void {
    $this->user->addPoints(amount: 100);

    expect($this->user->getLevel())->toBe(expected: 2)
        ->and($this->user->experience->level_id)->toBe(expected: 2)
        ->and($this->user->nextLevelAt())->toBe(expected: 150)
        ->and($this->user->experience->status->level)->toBe(expected: 2);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'experience_points' => 100,
        'level_id' => 2,
    ]);

    $this->user->addPoints(amount: 150);

    expect($this->user->getLevel())->toBe(expected: 3)
        ->and($this->user->getLevel())->toBe(expected: 3)
        ->and($this->user->experience->status->level)->toBe(expected: 3);

    $this->assertDatabaseHas(table: 'experiences', data: [
        'user_id' => $this->user->id,
        'level_id' => 3,
    ]);
});

test(description: 'points can be added with a reason, for the audit log', closure: function (): void {
    config()->set(key: 'level-up.audit.enabled', value: true);

    $this->user->addPoints(amount: 100, reason: 'test');

    $this->assertDatabaseHas(table: 'experience_audits', data: [
        'user_id' => $this->user->id,
        'points' => 100,
        'levelled_up' => false,
        'level_to' => null,
        'type' => AuditType::Add->value,
        'reason' => 'test',
    ]);
});

test(description: 'a User can level up multiple times', closure: function (): void {
    $this->user->addPoints(amount: 300);
    $this->user->addPoints(amount: 300);

    expect($this->user)->getLevel()->toBe(expected: 5);
});
