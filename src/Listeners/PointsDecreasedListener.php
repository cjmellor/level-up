<?php

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Models\Level;

class PointsDecreasedListener
{
    public function __invoke(PointsDecreased $event): void
    {
        /// don't create an audit record when the amount is 0
        if ($event->pointsDeducted === 0) {
            return;
        }

        if (config(key: 'level-up.audit.enabled')) {
            $event->user->experienceHistory()->create([
                'points' => -$event->pointsDeducted, // Note the negative sign to indicate points deduction
                'type' => $event->type,
                'reason' => $event->reason,
            ]);
        }

        // Get the next lower level experience needed for the user's current level
        $previousLevel = Level::firstWhere(column: 'level', operator: '=', value: $event->user->getLevel() - 1);

        if (! $previousLevel) {
            // If there is no previous level, return
            return;
        }

        // Check if user's points are less than the current level's required experience
        if ($event->user->getPoints() < $previousLevel->next_level_experience) {
            // Find the lowest level the user can be demoted to with current points
            $lowestDemotableLevel = Level::query()
                ->where(column: 'next_level_experience', operator: '>=', value: $event->user->getPoints())
                ->orderBy(column: 'level')
                ->first();

            // Update the user level to the lowest demotable level
            if ($lowestDemotableLevel->level < $event->user->getLevel()) {
                $event->user->levelDown(to: $lowestDemotableLevel->level);
            }
        }
    }
}
