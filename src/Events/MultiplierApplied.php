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
        public Model $user,
        public Collection $multipliers,
        public int $originalAmount,
        public int $finalAmount,
        public string $strategy,
    ) {}
}
