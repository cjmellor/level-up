<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class PointsIncreased
{
    use Dispatchable;

    public function __construct(
        public readonly int $pointsAdded,
        public readonly int $totalPoints,
        public readonly string $type,
        public readonly ?string $reason,
        public readonly Model $user,
        public readonly ?array $multipliers = null,
    ) {}
}
