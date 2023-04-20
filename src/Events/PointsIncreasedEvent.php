<?php

namespace LevelUp\Experience\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PointsIncreasedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $pointsAdded,
        public int $totalPoints,
    ) {
        //
    }
}
