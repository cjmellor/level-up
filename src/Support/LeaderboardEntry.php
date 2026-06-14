<?php

declare(strict_types=1);

namespace LevelUp\Experience\Support;

use Illuminate\Database\Eloquent\Model;

final readonly class LeaderboardEntry
{
    public function __construct(
        public Model $user,
        public int|float $score,
        public int $rank,
    ) {}
}
