<?php

declare(strict_types=1);

use LevelUp\Experience\Models\Multiplier;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Tests\Fixtures\User;

uses()->group('multiplier');

beforeEach(closure: function (): void {
    config()->set('level-up.multiplier.enabled', true);
});

test(description: 'a multiplier can be created', closure: function (): void {
    $multiplier = Multiplier::query()->create([
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
    Multiplier::query()->create([
        'name' => 'Zero',
        'multiplier' => 0,
        'is_active' => true,
    ]);
})->throws(InvalidArgumentException::class, 'Multiplier value must be at least 0.01.');

test(description: 'negative multiplier value is rejected', closure: function (): void {
    Multiplier::query()->create([
        'name' => 'Negative',
        'multiplier' => -1,
        'is_active' => true,
    ]);
})->throws(InvalidArgumentException::class, 'Multiplier value must be at least 0.01.');

test(description: 'active scope returns only active multipliers within time window', closure: function (): void {
    Multiplier::query()->create(['name' => 'Active', 'multiplier' => 2, 'is_active' => true]);
    Multiplier::query()->create(['name' => 'Inactive', 'multiplier' => 3, 'is_active' => false]);
    Multiplier::query()->create(['name' => 'Expired', 'multiplier' => 4, 'is_active' => true, 'starts_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);
    Multiplier::query()->create(['name' => 'Future', 'multiplier' => 5, 'is_active' => true, 'starts_at' => now()->addDay()]);
    Multiplier::query()->create(['name' => 'In Window', 'multiplier' => 6, 'is_active' => true, 'starts_at' => now()->subHour(), 'expires_at' => now()->addHour()]);

    $active = Multiplier::active()->pluck('name')->toArray();

    expect($active)->toBe(['Active', 'In Window']);
});

test(description: 'forUser scope returns global multipliers', closure: function (): void {
    Multiplier::query()->create(['name' => 'Global', 'multiplier' => 2, 'is_active' => true]);

    $this->user->addPoints(amount: 10);

    $multipliers = Multiplier::active()->forUser($this->user)->get();

    expect($multipliers)->toHaveCount(1)
        ->and($multipliers->first()->name)->toBe('Global');
});

test(description: 'forUser scope filters by user scope', closure: function (): void {
    $multiplier = Multiplier::query()->create(['name' => 'User Specific', 'multiplier' => 2, 'is_active' => true]);

    $multiplier->scopeToUser($this->user);

    $otherUser = User::query()->create(['name' => 'Other', 'email' => 'other@test.com', 'password' => bcrypt(value: 'password')]);

    $forUser = Multiplier::active()->forUser($this->user)->count();
    $forOther = Multiplier::active()->forUser($otherUser)->count();

    expect($forUser)->toBe(1)
        ->and($forOther)->toBe(0);
});

test(description: 'forUser scope filters by tier scope', closure: function (): void {
    config()->set('level-up.tiers.enabled', true);

    $silverTier = Tier::query()->create(['name' => 'Silver', 'experience' => 100]);

    $multiplier = Multiplier::query()->create(['name' => 'Silver Bonus', 'multiplier' => 2, 'is_active' => true]);
    $multiplier->scopeToTier($silverTier);

    $this->user->addPoints(amount: 10);
    $this->user->experience->update(['tier_id' => $silverTier->id]);

    $multipliers = Multiplier::active()->forUser($this->user->fresh())->get();

    expect($multipliers)->toHaveCount(1)
        ->and($multipliers->first()->name)->toBe('Silver Bonus');
});

test(description: 'scheduled scope returns future multipliers', closure: function (): void {
    Multiplier::query()->create(['name' => 'Active Now', 'multiplier' => 2, 'is_active' => true]);
    Multiplier::query()->create(['name' => 'Scheduled', 'multiplier' => 3, 'is_active' => true, 'starts_at' => now()->addDay()]);

    $scheduled = Multiplier::scheduled()->pluck('name')->toArray();

    expect($scheduled)->toBe(['Scheduled']);
});

test(description: 'expired scope returns past multipliers', closure: function (): void {
    Multiplier::query()->create(['name' => 'Active', 'multiplier' => 2, 'is_active' => true]);
    Multiplier::query()->create(['name' => 'Expired', 'multiplier' => 3, 'is_active' => true, 'expires_at' => now()->subDay()]);

    $expired = Multiplier::expired()->pluck('name')->toArray();

    expect($expired)->toBe(['Expired']);
});

test(description: 'fractional multiplier values are supported', closure: function (): void {
    $multiplier = Multiplier::query()->create([
        'name' => 'Half',
        'multiplier' => 0.5,
        'is_active' => true,
    ]);

    expect($multiplier->multiplier)->toBe('0.50');
});

test(description: 'starts_at must be before expires_at', closure: function (): void {
    Multiplier::query()->create([
        'name' => 'Invalid',
        'multiplier' => 2,
        'is_active' => true,
        'starts_at' => now()->addDay(),
        'expires_at' => now(),
    ]);
})->throws(InvalidArgumentException::class, 'starts_at must be before expires_at.');

test(description: 'scopeToUser attaches the user to the multiplier', closure: function (): void {
    $multiplier = Multiplier::query()->create(['name' => 'Scoped', 'multiplier' => 2, 'is_active' => true]);

    $multiplier->scopeToUser($this->user);

    expect($multiplier->users)->toHaveCount(1)
        ->and($multiplier->users->first()->id)->toBe($this->user->id);
});

test(description: 'scopeToUser is idempotent for the same user', closure: function (): void {
    $multiplier = Multiplier::query()->create(['name' => 'Scoped', 'multiplier' => 2, 'is_active' => true]);

    $multiplier->scopeToUser($this->user);
    $multiplier->scopeToUser($this->user);

    expect($multiplier->users()->count())->toBe(1);
});

test(description: 'scopeToTier attaches the tier to the multiplier', closure: function (): void {
    $tier = Tier::query()->create(['name' => 'Gold', 'experience' => 500]);
    $multiplier = Multiplier::query()->create(['name' => 'Tier Bonus', 'multiplier' => 3, 'is_active' => true]);

    $multiplier->scopeToTier($tier);

    expect($multiplier->tiers)->toHaveCount(1)
        ->and($multiplier->tiers->first()->name)->toBe('Gold');
});

test(description: 'scopeToTier is idempotent for the same tier', closure: function (): void {
    $tier = Tier::query()->create(['name' => 'Gold', 'experience' => 500]);
    $multiplier = Multiplier::query()->create(['name' => 'Tier Bonus', 'multiplier' => 3, 'is_active' => true]);

    $multiplier->scopeToTier($tier);
    $multiplier->scopeToTier($tier);

    expect($multiplier->tiers()->count())->toBe(1);
});

test(description: 'unscopeFromUser detaches the user from the multiplier', closure: function (): void {
    $multiplier = Multiplier::query()->create(['name' => 'Scoped', 'multiplier' => 2, 'is_active' => true]);
    $multiplier->scopeToUser($this->user);

    expect($multiplier->users()->count())->toBe(1);

    $multiplier->unscopeFromUser($this->user);

    expect($multiplier->users()->count())->toBe(0);
});

test(description: 'unscopeFromTier detaches the tier from the multiplier', closure: function (): void {
    $tier = Tier::query()->create(['name' => 'Gold', 'experience' => 500]);
    $multiplier = Multiplier::query()->create(['name' => 'Tier Bonus', 'multiplier' => 3, 'is_active' => true]);
    $multiplier->scopeToTier($tier);

    expect($multiplier->tiers()->count())->toBe(1);

    $multiplier->unscopeFromTier($tier);

    expect($multiplier->tiers()->count())->toBe(0);
});

test(description: 'isGlobal returns true for a multiplier without any scopes', closure: function (): void {
    $multiplier = Multiplier::query()->create(['name' => 'Global', 'multiplier' => 2, 'is_active' => true]);

    expect($multiplier->isGlobal())->toBeTrue();
});

test(description: 'isGlobal returns false once a user or tier scope is attached', closure: function (): void {
    $multiplier = Multiplier::query()->create(['name' => 'User Only', 'multiplier' => 2, 'is_active' => true]);
    $multiplier->scopeToUser($this->user);

    expect($multiplier->isGlobal())->toBeFalse();

    $tier = Tier::query()->create(['name' => 'Gold', 'experience' => 500]);
    $multiplier2 = Multiplier::query()->create(['name' => 'Tier Only', 'multiplier' => 2, 'is_active' => true]);
    $multiplier2->scopeToTier($tier);

    expect($multiplier2->isGlobal())->toBeFalse();
});

test(description: 'multiplier scoped to both user and tier is only applied once', closure: function (): void {
    config()->set('level-up.tiers.enabled', true);

    $tier = Tier::query()->create(['name' => 'Gold', 'experience' => 100]);

    $multiplier = Multiplier::query()->create(['name' => 'Dual Scoped', 'multiplier' => 3, 'is_active' => true]);
    $multiplier->scopeToUser($this->user);
    $multiplier->scopeToTier($tier);

    $this->user->addPoints(amount: 10);
    $this->user->experience->update(['tier_id' => $tier->id]);

    $matched = Multiplier::active()->forUser($this->user->fresh())->get();

    expect($matched)->toHaveCount(1)
        ->and($matched->first()->name)->toBe('Dual Scoped');
});
