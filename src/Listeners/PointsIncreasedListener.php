<?php

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Models\Level;

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

        // Get the next level experience needed for the user's current level
        $nextLevel = $levelModel::firstWhere(column: 'level', operator: '=', value: $event->user->getLevel() + 1);

        if (! $nextLevel) {
            // If there is no next level, return
            return;
        }

        // Check if user's points are equal or greater than the next level's required experience
        if ($event->user->getPoints() >= $nextLevel->next_level_experience) {
            // Find the highest level the user can achieve with current points
            $highestAchievableLevel = $levelModel::query()
                ->where(column: 'next_level_experience', operator: '<=', value: $event->user->getPoints())
                ->orderByDesc(column: 'level')
                ->first();

            // Update the user level to the highest achievable level
            if ($highestAchievableLevel->level > $event->user->getLevel()) {
                $event->user->levelUp(to: $highestAchievableLevel->level);
            }
        }
    }
}
