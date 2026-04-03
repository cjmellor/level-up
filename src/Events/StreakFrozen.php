<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Support\Carbon;

class StreakFrozen
{
    public function __construct(
        public readonly int $frozenStreakLength,
        public readonly Carbon $frozenUntil,
    ) {}
}
