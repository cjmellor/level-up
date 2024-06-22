<?php

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class PointsDecreased
{
    use Dispatchable;

    public function __construct(
        public int $pointsDecreasedBy,
        public int $totalPoints,
        public ?string $reason,
        public Model $user,
    ) {}
}
