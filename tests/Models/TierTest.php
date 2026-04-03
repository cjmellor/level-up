<?php

declare(strict_types=1);

use LevelUp\Experience\Exceptions\TierExistsException;
use LevelUp\Experience\Models\Tier;

uses()->group('tiers');

it(description: 'can create a tier', closure: function (): void {
    $tiers = Tier::add([
        'name' => 'Bronze',
        'experience' => 0,
    ]);

    expect(value: $tiers[0]->name)->toBe(expected: 'Bronze')
        ->and($tiers[0]->experience)->toBe(expected: 0);

    $this->assertDatabaseHas(table: 'tiers', data: [
        'name' => 'Bronze',
        'experience' => 0,
    ]);
});

it(description: 'can create multiple tiers', closure: function (): void {
    $tiers = Tier::add(
        ['name' => 'Bronze', 'experience' => 0],
        ['name' => 'Silver', 'experience' => 500],
        ['name' => 'Gold', 'experience' => 2000],
    );

    expect(value: $tiers)->toHaveCount(count: 3)
        ->and($tiers[0]->name)->toBe(expected: 'Bronze')
        ->and($tiers[1]->name)->toBe(expected: 'Silver')
        ->and($tiers[2]->name)->toBe(expected: 'Gold');
});

it(description: 'can create a tier with metadata', closure: function (): void {
    $tiers = Tier::add([
        'name' => 'Gold',
        'experience' => 2000,
        'metadata' => ['color' => '#FFD700', 'icon' => 'star'],
    ]);

    expect(value: $tiers[0]->metadata)->toBe(expected: ['color' => '#FFD700', 'icon' => 'star']);
});

it(description: 'throws an error if a tier exists', closure: function (): void {
    Tier::add(['name' => 'Bronze', 'experience' => 0]);
    Tier::add(['name' => 'Bronze', 'experience' => 100]);
})->throws(exception: TierExistsException::class, exceptionMessage: 'The tier with name "Bronze" already exists');
