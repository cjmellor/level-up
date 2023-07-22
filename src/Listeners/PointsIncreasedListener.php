<?php

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Models\Level;

class PointsIncreasedListener
{
    public function __invoke(PointsIncreased $event): void
    {
        if (config(key: 'level-up.audit.enabled')) {
            $event->user->experienceHistory()->create([
                'points' => $event->pointsAdded,
                'type' => $event->type,
                'reason' => $event->reason,
            ]);
        }

        if (Level::count() === 0) {
            Level::add([
                'level' => config(key: 'level-up.starting_level'),
                'next_level_experience' => null,
            ]);
        }

        $nextLevel = Level::firstWhere(column: 'level', operator: $event->user->getLevel() + 1);

        if (! $nextLevel) {
            return;
        }

        if ($event->user->getPoints() < $nextLevel->next_level_experience) {
            return;
        }

        $event->user->levelUp();
    }
}
