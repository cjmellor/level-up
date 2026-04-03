<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

class MultiplierApplied
{
    use Dispatchable;

    public function __construct(
        public readonly Model $user,
        public readonly Collection $multipliers,
        public readonly int $originalAmount,
        public readonly int $finalAmount,
        public readonly string $strategy,
    ) {}
}
