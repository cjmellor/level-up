<?php

declare(strict_types=1);

use LevelUp\Experience\Models\Challenge;

uses()->group('challenges');

test(description: 'active scope excludes expired challenges', closure: function (): void {
    Challenge::factory()->expiresAt(now()->subDay())->create();

    expect(Challenge::active()->count())->toBe(expected: 0);
});

test(description: 'active scope excludes not-yet-started challenges', closure: function (): void {
    Challenge::factory()->startsAt(now()->addDay())->create();

    expect(Challenge::active()->count())->toBe(expected: 0);
});

test(description: 'active scope includes challenges with no dates set', closure: function (): void {
    Challenge::factory()->create();

    expect(Challenge::active()->count())->toBe(expected: 1);
});

test(description: 'active scope includes challenges within their time window', closure: function (): void {
    Challenge::factory()
        ->startsAt(now()->subDay())
        ->expiresAt(now()->addDay())
        ->create();

    expect(Challenge::active()->count())->toBe(expected: 1);
});

test(description: 'autoEnroll scope filters correctly', closure: function (): void {
    Challenge::factory()->create(['auto_enroll' => false]);
    Challenge::factory()->autoEnroll()->create();

    expect(Challenge::autoEnroll()->count())->toBe(expected: 1);
});
