<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class UserLeftTrackedDepth
{
    use Dispatchable;

    public function __construct(
        public readonly Model $user,
        public readonly string $board,
        public readonly int $previousRank,
    ) {}
}
