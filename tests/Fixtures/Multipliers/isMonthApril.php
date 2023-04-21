<?php

namespace LevelUp\Experience\Tests\Fixtures\Multipliers;

use LevelUp\Experience\Contracts\Multiplier;

class isMonthApril implements Multiplier
{

    public function qualifies(): bool
    {
        return now()->month === 4;
    }

    public function setMultiplier(): int
    {
        return 5;
    }
}
