<?php

declare(strict_types=1);

use LevelUp\Experience\Models\Tier;

uses()->group('tiers', 'listeners');

beforeEach(closure: function (): void {
    config()->set('level-up.multiplier.enabled', false);

    Tier::add(
        ['name' => 'Bronze', 'experience' => 0],
        ['name' => 'Silver', 'experience' => 500],
    );
});

test(description: 'tier promotion creates an audit record when enabled', closure: function (): void {
    config()->set('level-up.audit.enabled', true);

    $this->user->addPoints(amount: 550);

    $auditRecord = $this->user->experienceHistory()
        ->where('type', 'tier_up')
        ->first();

    expect($auditRecord)->not->toBeNull()
        ->and($auditRecord->reason)->toBe(expected: 'None → Silver');
});

test(description: 'tier promotion does not create an audit record when disabled', closure: function (): void {
    config()->set('level-up.audit.enabled', false);

    $this->user->addPoints(amount: 550);

    $auditRecord = $this->user->experienceHistory()
        ->where('type', 'tier_up')
        ->first();

    expect($auditRecord)->toBeNull();
});

test(description: 'tier demotion creates an audit record when enabled', closure: function (): void {
    config()->set('level-up.audit.enabled', true);
    config()->set('level-up.tiers.demotion', true);

    $this->user->addPoints(amount: 550);
    $this->user->deductPoints(amount: 400);

    $auditRecord = $this->user->experienceHistory()
        ->where('type', 'tier_down')
        ->first();

    expect($auditRecord)->not->toBeNull()
        ->and($auditRecord->reason)->toBe(expected: 'Silver → Bronze');
});
