<?php

declare(strict_types=1);

namespace LevelUp\Experience\Concerns;

use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use LevelUp\Experience\Models\Tier;

trait HasTiers
{
    public function getTier(): ?Tier
    {
        if (! config(key: 'level-up.tiers.enabled')) {
            return null;
        }

        if ($this->experience()->doesntExist()) {
            return null;
        }

        if (config(key: 'level-up.tiers.demotion')) {
            return $this->computeTierFromPoints();
        }

        return $this->storedTier() ?? $this->computeTierFromPoints();
    }

    public function getNextTier(?Tier $currentTier = null): ?Tier
    {
        $currentTier ??= $this->getTier();

        if (! $currentTier) {
            return $this->lowestTier();
        }

        $tierClass = config(key: 'level-up.models.tier');

        return $tierClass::query()
            ->where(column: 'experience', operator: '>', value: $currentTier->experience)
            ->orderBy(column: 'experience')
            ->first();
    }

    public function tierProgress(): int
    {
        $currentTier = $this->getTier();

        if (! $currentTier) {
            return 0;
        }

        $nextTier = $this->getNextTier($currentTier);

        if (! $nextTier) {
            return 100;
        }

        $range = $nextTier->experience - $currentTier->experience;

        if ($range <= 0) {
            return 100;
        }

        return max(0, min(100, (int) ((($this->getPoints() - $currentTier->experience) / $range) * 100)));
    }

    public function nextTierAt(): int
    {
        $currentTier = $this->getTier();
        $nextTier = $this->getNextTier($currentTier);

        if (! $nextTier) {
            return 0;
        }

        return max(0, $nextTier->experience - $this->getPoints());
    }

    public function isAtTier(string $name): bool
    {
        return $this->getTier()?->name === $name;
    }

    public function isAtOrAboveTier(string $name): bool
    {
        $currentTier = $this->getTier();
        $tierClass = config(key: 'level-up.models.tier');

        $targetTier = $tierClass::query()
            ->where(column: 'name', operator: '=', value: $name)
            ->first();

        if (! $currentTier || ! $targetTier) {
            return false;
        }

        return $currentTier->experience >= $targetTier->experience;
    }

    public function tier(): HasOneThrough
    {
        $experienceClass = config(key: 'level-up.models.experience');
        $tierClass = config(key: 'level-up.models.tier');

        return $this->hasOneThrough(
            related: $tierClass,
            through: $experienceClass,
            firstKey: config(key: 'level-up.user.foreign_key'),
            secondKey: 'id',
            localKey: 'id',
            secondLocalKey: 'tier_id',
        );
    }

    protected function computeTierFromPoints(): ?Tier
    {
        $tierClass = config(key: 'level-up.models.tier');

        return $tierClass::forPoints(points: $this->getPoints());
    }

    protected function storedTier(): ?Tier
    {
        return $this->tier;
    }

    protected function lowestTier(): ?Tier
    {
        $tierClass = config(key: 'level-up.models.tier');

        return $tierClass::query()
            ->orderBy(column: 'experience')
            ->first();
    }
}
