<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class PointsDecreased
{
    use Dispatchable;

    public function __construct(
        public readonly int $pointsDecreasedBy,
        public readonly int $totalPoints,
        public readonly ?string $reason,
        public readonly Model $user,
    ) {}
}
