<?php

namespace LevelUp\Experience\Tests\Fixtures\Multipliers;

use LevelUp\Experience\Contracts\Multiplier;

class isMonthApril implements Multiplier
{
    public bool $enabled = true;

    public function qualifies(array $data): bool
    {
        return now()->month === 4;
    }

    public function setMultiplier(): int
    {
        return 5;
    }
}
