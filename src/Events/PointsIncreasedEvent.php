<?php

namespace LevelUp\Experience\Events;

use Illuminate\Queue\SerializesModels;

class PointsIncreasedEvent
{
    use SerializesModels;

    public function __construct(
        public int $pointsAdded,
        public int $totalPoints,
    ) {
    }
}
