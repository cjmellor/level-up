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

        $nextLevel = Level::where('level', $event->user->getLevel() + 1)->first();

        if (! $nextLevel) {
            return;
        }

        if ($event->user->getPoints() < $nextLevel->next_level_experience) {
            return;
        }

        $event->user->levelUp();
    }
}
