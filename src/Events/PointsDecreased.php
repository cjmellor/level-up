<?php

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class PointsDecreased
{
    use Dispatchable;

    public function __construct(
        public int     $pointsDeducted,
        public int     $totalPoints,
        public string  $type,
        public ?string $reason,
        public Model   $user
    )
    {
    }
}
