<?php

namespace LevelUp\Experience\Events;

use Illuminate\Support\Carbon;

class StreakFrozen
{
    public function __construct(
        public int $frozenStreakLength,
        public Carbon $frozenUntil,
    ) {
    }
}
