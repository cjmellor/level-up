<?php

namespace LevelUp\Experience\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PointsDecreased
{
    use Dispatchable;

    public function __construct(
        public int $pointsDecreasedBy,
        public int $totalPoints,
    ) {
    }
}
