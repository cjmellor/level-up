<?php

declare(strict_types=1);

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Enums\TierDirection;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Events\UserTierUpdated;

class PointsDecreasedListener
{
    public function __invoke(PointsDecreased $event): void
    {
        if ($event->pointsDecreasedBy === 0) {
            return;
        }

        if (config(key: 'level-up.audit.enabled')) {
            $event->user->experienceHistory()->create(attributes: [
                'points' => $event->pointsDecreasedBy,
                'type' => AuditType::Remove->value,
                'reason' => $event->reason,
            ]);
        }

        $this->checkTierDemotion($event);
    }

    protected function checkTierDemotion(PointsDecreased $event): void
    {
        if (! config(key: 'level-up.tiers.enabled')) {
            return;
        }

        if (! config(key: 'level-up.tiers.demotion')) {
            return;
        }

        $tierClass = config(key: 'level-up.models.tier');
        $currentTierId = $event->user->experience?->tier_id;

        if (! $currentTierId) {
            return;
        }

        $newTier = $tierClass::forPoints(points: $event->totalPoints);

        if ($newTier && $newTier->id === $currentTierId) {
            return;
        }

        $currentTier = $tierClass::find($currentTierId);

        $event->user->experience->update(['tier_id' => $newTier?->id]);

        event(new UserTierUpdated(
            user: $event->user,
            previousTier: $currentTier,
            newTier: $newTier,
            direction: TierDirection::Demoted,
        ));
    }
}
