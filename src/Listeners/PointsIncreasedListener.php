<?php

namespace LevelUp\Experience\Listeners;

use LevelUp\Experience\Events\PointsIncreasedEvent;
use LevelUp\Experience\Models\Level;

class PointsIncreasedListener
{
    public function __invoke(PointsIncreasedEvent $event): void
    {
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
