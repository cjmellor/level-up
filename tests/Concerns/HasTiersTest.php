<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use LevelUp\Experience\Enums\TierDirection;
use LevelUp\Experience\Events\UserTierUpdated;
use LevelUp\Experience\Models\Tier;

uses()->group('tiers');

beforeEach(closure: function (): void {
    config()->set('level-up.multiplier.enabled', false);

    Tier::add(
        ['name' => 'Bronze', 'experience' => 0],
        ['name' => 'Silver', 'experience' => 500],
        ['name' => 'Gold', 'experience' => 2000],
        ['name' => 'Platinum', 'experience' => 5000],
    );
});

test(description: 'a User has no tier when they have no experience', closure: function (): void {
    expect($this->user->getTier())->toBeNull();
});

test(description: 'a User gets a tier when they earn enough points', closure: function (): void {
    $this->user->addPoints(amount: 10);

    expect($this->user->getTier())
        ->toBeInstanceOf(Tier::class)
        ->name->toBe(expected: 'Bronze');
});

test(description: 'a User tier updates when they earn more points', closure: function (): void {
    $this->user->addPoints(amount: 550);

    expect($this->user->fresh()->getTier())
        ->name->toBe(expected: 'Silver');
});

test(description: 'a User reaches the highest tier', closure: function (): void {
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);

    expect($this->user->fresh()->getTier())
        ->name->toBe(expected: 'Platinum');
});

test(description: 'getNextTier returns the next tier above current', closure: function (): void {
    $this->user->addPoints(amount: 10);

    expect($this->user->fresh()->getNextTier())
        ->name->toBe(expected: 'Silver');
});

test(description: 'getNextTier returns null when at highest tier', closure: function (): void {
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);

    expect($this->user->fresh()->getNextTier())->toBeNull();
});

test(description: 'getNextTier returns the lowest tier when user has no tier', closure: function (): void {
    expect($this->user->getNextTier())
        ->name->toBe(expected: 'Bronze');
});

test(description: 'tierProgress returns percentage through current bracket', closure: function (): void {
    $this->user->addPoints(amount: 250);

    expect($this->user->fresh()->tierProgress())->toBe(expected: 50);
});

test(description: 'tierProgress returns 100 when at highest tier', closure: function (): void {
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);

    expect($this->user->fresh()->tierProgress())->toBe(expected: 100);
});

test(description: 'tierProgress returns 0 when user has no tier', closure: function (): void {
    expect($this->user->tierProgress())->toBe(expected: 0);
});

test(description: 'nextTierAt returns XP remaining until next tier', closure: function (): void {
    $this->user->addPoints(amount: 250);

    expect($this->user->fresh()->nextTierAt())->toBe(expected: 250);
});

test(description: 'nextTierAt returns 0 when at highest tier', closure: function (): void {
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);
    $this->user->addPoints(amount: 600);

    expect($this->user->fresh()->nextTierAt())->toBe(expected: 0);
});

test(description: 'isAtTier checks the current tier name', closure: function (): void {
    $this->user->addPoints(amount: 10);

    expect($this->user->fresh()->isAtTier(name: 'Bronze'))->toBeTrue()
        ->and($this->user->fresh()->isAtTier(name: 'Silver'))->toBeFalse();
});

test(description: 'isAtOrAboveTier checks tier hierarchy', closure: function (): void {
    $this->user->addPoints(amount: 550);

    expect($this->user->fresh()->isAtOrAboveTier(name: 'Bronze'))->toBeTrue()
        ->and($this->user->fresh()->isAtOrAboveTier(name: 'Silver'))->toBeTrue()
        ->and($this->user->fresh()->isAtOrAboveTier(name: 'Gold'))->toBeFalse();
});

test(description: 'getTier returns null when tiers are disabled', closure: function (): void {
    config()->set('level-up.tiers.enabled', false);

    $this->user->addPoints(amount: 550);

    expect($this->user->fresh()->getTier())->toBeNull();
});

test(description: 'UserTierUpdated event fires on tier promotion', closure: function (): void {
    Event::fake([UserTierUpdated::class]);

    $this->user->addPoints(amount: 10);

    Event::assertDispatched(
        event: UserTierUpdated::class,
        callback: fn (UserTierUpdated $event): bool => $event->newTier->name === 'Bronze'
            && ! $event->previousTier instanceof Tier
            && $event->direction === TierDirection::Promoted
            && $event->user->is($this->user)
    );
});

test(description: 'UserTierUpdated event fires when crossing tier boundaries', closure: function (): void {
    Event::fake([UserTierUpdated::class]);

    $this->user->addPoints(amount: 550);

    Event::assertDispatched(
        event: UserTierUpdated::class,
        callback: fn (UserTierUpdated $event): bool => $event->newTier->name === 'Silver'
    );
});

test(description: 'UserTierUpdated does not fire when staying in same tier', closure: function (): void {
    $this->user->addPoints(amount: 10);

    Event::fake([UserTierUpdated::class]);

    $this->user->addPoints(amount: 20);

    Event::assertNotDispatched(event: UserTierUpdated::class);
});

test(description: 'tier is preserved when points are deducted and demotion is disabled', closure: function (): void {
    config()->set('level-up.tiers.demotion', false);

    $this->user->addPoints(amount: 550);

    expect($this->user->fresh()->getTier())->name->toBe(expected: 'Silver');

    $this->user->deductPoints(amount: 400);

    expect($this->user->fresh()->getTier())->name->toBe(expected: 'Silver');
});

test(description: 'tier drops when points are deducted and demotion is enabled', closure: function (): void {
    config()->set('level-up.tiers.demotion', true);

    $this->user->addPoints(amount: 550);

    expect($this->user->fresh()->getTier())->name->toBe(expected: 'Silver');

    $this->user->deductPoints(amount: 400);

    expect($this->user->fresh()->getTier())->name->toBe(expected: 'Bronze');
});

test(description: 'UserTierUpdated event fires on demotion', closure: function (): void {
    config()->set('level-up.tiers.demotion', true);

    $this->user->addPoints(amount: 550);

    Event::fake([UserTierUpdated::class]);

    $this->user->deductPoints(amount: 400);

    Event::assertDispatched(
        event: UserTierUpdated::class,
        callback: fn (UserTierUpdated $event): bool => $event->direction === TierDirection::Demoted
            && $event->previousTier->name === 'Silver'
            && $event->newTier->name === 'Bronze'
    );
});

test(description: 'demotion event does not fire when demotion is disabled', closure: function (): void {
    config()->set('level-up.tiers.demotion', false);

    $this->user->addPoints(amount: 550);

    Event::fake([UserTierUpdated::class]);

    $this->user->deductPoints(amount: 400);

    Event::assertNotDispatched(event: UserTierUpdated::class);
});

test(description: 'the stored tier_id is set on the experience record', closure: function (): void {
    $this->user->addPoints(amount: 550);

    $silverTier = Tier::query()->where('name', 'Silver')->first();

    expect($this->user->fresh()->experience->tier_id)->toBe(expected: $silverTier->id);
});

test(description: 'isAtTier returns false for a non-existent tier name', closure: function (): void {
    $this->user->addPoints(amount: 550);

    expect($this->user->isAtTier(name: 'Diamond'))->toBeFalse();
});

test(description: 'isAtOrAboveTier returns false for a non-existent tier name', closure: function (): void {
    $this->user->addPoints(amount: 550);

    expect($this->user->isAtOrAboveTier(name: 'Diamond'))->toBeFalse();
});

test(description: 'demotion below all tiers sets tier to null', closure: function (): void {
    config()->set('level-up.tiers.demotion', true);

    Tier::query()->delete();
    Tier::add(
        ['name' => 'Bronze', 'experience' => 100],
    );

    $this->user->addPoints(amount: 150);

    expect($this->user->fresh()->getTier())->name->toBe(expected: 'Bronze');

    Event::fake([UserTierUpdated::class]);

    $this->user->deductPoints(amount: 100);

    expect($this->user->fresh()->experience->tier_id)->toBeNull();

    Event::assertDispatched(
        event: UserTierUpdated::class,
        callback: fn (UserTierUpdated $event): bool => $event->direction === TierDirection::Demoted
            && $event->previousTier->name === 'Bronze'
            && ! $event->newTier instanceof Tier
    );
});
