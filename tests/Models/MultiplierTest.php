<?php

declare(strict_types=1);

use LevelUp\Experience\Models\Multiplier;
use LevelUp\Experience\Models\MultiplierScope;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Tests\Fixtures\User;

uses()->group('multiplier');

beforeEach(closure: function (): void {
    config()->set('level-up.multiplier.enabled', true);
});

test(description: 'a multiplier can be created', closure: function (): void {
    $multiplier = Multiplier::create([
        'name' => 'Double XP',
        'multiplier' => 2,
        'is_active' => true,
    ]);

    expect($multiplier)
        ->name->toBe('Double XP')
        ->multiplier->toBe('2.00')
        ->is_active->toBeTrue();
});

test(description: 'multiplier value must be greater than zero', closure: function (): void {
    Multiplier::create([
        'name' => 'Zero',
        'multiplier' => 0,
        'is_active' => true,
    ]);
})->throws(InvalidArgumentException::class, 'Multiplier value must be greater than 0.');

test(description: 'negative multiplier value is rejected', closure: function (): void {
    Multiplier::create([
        'name' => 'Negative',
        'multiplier' => -1,
        'is_active' => true,
    ]);
})->throws(InvalidArgumentException::class, 'Multiplier value must be greater than 0.');

test(description: 'active scope returns only active multipliers within time window', closure: function (): void {
    Multiplier::create(['name' => 'Active', 'multiplier' => 2, 'is_active' => true]);
    Multiplier::create(['name' => 'Inactive', 'multiplier' => 3, 'is_active' => false]);
    Multiplier::create(['name' => 'Expired', 'multiplier' => 4, 'is_active' => true, 'starts_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);
    Multiplier::create(['name' => 'Future', 'multiplier' => 5, 'is_active' => true, 'starts_at' => now()->addDay()]);
    Multiplier::create(['name' => 'In Window', 'multiplier' => 6, 'is_active' => true, 'starts_at' => now()->subHour(), 'expires_at' => now()->addHour()]);

    $active = Multiplier::active()->pluck('name')->toArray();

    expect($active)->toBe(['Active', 'In Window']);
});

test(description: 'forUser scope returns global multipliers', closure: function (): void {
    Multiplier::create(['name' => 'Global', 'multiplier' => 2, 'is_active' => true]);

    $this->user->addPoints(amount: 10);

    $multipliers = Multiplier::active()->forUser($this->user)->get();

    expect($multipliers)->toHaveCount(1)
        ->and($multipliers->first()->name)->toBe('Global');
});

test(description: 'forUser scope filters by user scope', closure: function (): void {
    $multiplier = Multiplier::create(['name' => 'User Specific', 'multiplier' => 2, 'is_active' => true]);

    $multiplier->scopes()->create([
        'scopeable_type' => User::class,
        'scopeable_id' => $this->user->id,
    ]);

    $otherUser = User::create(['name' => 'Other', 'email' => 'other@test.com', 'password' => bcrypt('password')]);

    $forUser = Multiplier::active()->forUser($this->user)->count();
    $forOther = Multiplier::active()->forUser($otherUser)->count();

    expect($forUser)->toBe(1)
        ->and($forOther)->toBe(0);
});

test(description: 'forUser scope filters by tier scope', closure: function (): void {
    config()->set('level-up.tiers.enabled', true);

    $silverTier = Tier::create(['name' => 'Silver', 'experience' => 100]);

    $multiplier = Multiplier::create(['name' => 'Silver Bonus', 'multiplier' => 2, 'is_active' => true]);
    $multiplier->scopes()->create([
        'scopeable_type' => Tier::class,
        'scopeable_id' => $silverTier->id,
    ]);

    $this->user->addPoints(amount: 10);
    $this->user->experience->update(['tier_id' => $silverTier->id]);

    $multipliers = Multiplier::active()->forUser($this->user->fresh())->get();

    expect($multipliers)->toHaveCount(1)
        ->and($multipliers->first()->name)->toBe('Silver Bonus');
});

test(description: 'scheduled scope returns future multipliers', closure: function (): void {
    Multiplier::create(['name' => 'Active Now', 'multiplier' => 2, 'is_active' => true]);
    Multiplier::create(['name' => 'Scheduled', 'multiplier' => 3, 'is_active' => true, 'starts_at' => now()->addDay()]);

    $scheduled = Multiplier::scheduled()->pluck('name')->toArray();

    expect($scheduled)->toBe(['Scheduled']);
});

test(description: 'expired scope returns past multipliers', closure: function (): void {
    Multiplier::create(['name' => 'Active', 'multiplier' => 2, 'is_active' => true]);
    Multiplier::create(['name' => 'Expired', 'multiplier' => 3, 'is_active' => true, 'expires_at' => now()->subDay()]);

    $expired = Multiplier::expired()->pluck('name')->toArray();

    expect($expired)->toBe(['Expired']);
});

test(description: 'multiplier has many scopes relationship', closure: function (): void {
    $multiplier = Multiplier::create(['name' => 'Scoped', 'multiplier' => 2, 'is_active' => true]);

    $multiplier->scopes()->createMany([
        ['scopeable_type' => User::class, 'scopeable_id' => 1],
        ['scopeable_type' => Tier::class, 'scopeable_id' => 1],
    ]);

    expect($multiplier->scopes)->toHaveCount(2);
});

test(description: 'multiplier scopes are associated correctly', closure: function (): void {
    $multiplier = Multiplier::create(['name' => 'Scoped', 'multiplier' => 2, 'is_active' => true]);
    $multiplier->scopes()->create(['scopeable_type' => User::class, 'scopeable_id' => 1]);

    expect(MultiplierScope::count())->toBe(1)
        ->and(MultiplierScope::first()->multiplier_id)->toBe($multiplier->id);
});

test(description: 'fractional multiplier values are supported', closure: function (): void {
    $multiplier = Multiplier::create([
        'name' => 'Half',
        'multiplier' => 0.5,
        'is_active' => true,
    ]);

    expect($multiplier->multiplier)->toBe('0.50');
});
