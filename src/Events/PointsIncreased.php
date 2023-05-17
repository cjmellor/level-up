<?php

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PointsIncreased
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $pointsAdded,
        public int $totalPoints,
        public string $type,
        public ?string $reason,
        public Model $user,
    ) {
        //
    }
}
