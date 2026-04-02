<?php

declare(strict_types=1);

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Enums\TierDirection;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\UserTierUpdated;

class PointsIncreasedListener
{
    public function __invoke(PointsIncreased $event): void
    {
        $levelModel = config(key: 'level-up.models.level');

        if (config(key: 'level-up.audit.enabled')) {
            $event->user->experienceHistory()->create([
                'points' => $event->pointsAdded,
                'type' => $event->type,
                'reason' => $event->reason,
                'multipliers' => $event->multipliers,
            ]);
        }

        $nextLevel = $levelModel::firstWhere(column: 'level', operator: '=', value: $event->user->getLevel() + 1);

        if ($nextLevel && $event->user->getPoints() >= $nextLevel->next_level_experience) {
            $highestAchievableLevel = $levelModel::query()
                ->where(column: 'next_level_experience', operator: '<=', value: $event->user->getPoints())
                ->orderByDesc(column: 'level')
                ->first();

            if ($highestAchievableLevel && $highestAchievableLevel->level > $event->user->getLevel()) {
                $event->user->levelUp(to: $highestAchievableLevel->level);
            }
        }

        $this->checkTierPromotion($event);
    }

    protected function checkTierPromotion(PointsIncreased $event): void
    {
        if (! config(key: 'level-up.tiers.enabled')) {
            return;
        }

        $tierClass = config(key: 'level-up.models.tier');
        $newTier = $tierClass::forPoints(points: $event->totalPoints);

        if (! $newTier) {
            return;
        }

        $currentTierId = $event->user->experience?->tier_id;

        if ($currentTierId === $newTier->id) {
            return;
        }

        $previousTier = $currentTierId ? $tierClass::find($currentTierId) : null;

        if ($previousTier && $newTier->experience <= $previousTier->experience) {
            return;
        }

        $event->user->experience->update(['tier_id' => $newTier->id]);

        event(new UserTierUpdated(
            user: $event->user,
            previousTier: $previousTier,
            newTier: $newTier,
            direction: TierDirection::Promoted,
        ));
    }
}
