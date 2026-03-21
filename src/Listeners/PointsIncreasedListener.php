<?php

declare(strict_types=1);

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Events\PointsIncreased;

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
            ]);
        }

        $nextLevel = $levelModel::firstWhere(column: 'level', operator: '=', value: $event->user->getLevel() + 1);

        if (! $nextLevel) {
            return;
        }

        if ($event->user->getPoints() >= $nextLevel->next_level_experience) {
            $highestAchievableLevel = $levelModel::query()
                ->where(column: 'next_level_experience', operator: '<=', value: $event->user->getPoints())
                ->orderByDesc(column: 'level')
                ->first();

            if ($highestAchievableLevel && $highestAchievableLevel->level > $event->user->getLevel()) {
                $event->user->levelUp(to: $highestAchievableLevel->level);
            }
        }
    }
}
