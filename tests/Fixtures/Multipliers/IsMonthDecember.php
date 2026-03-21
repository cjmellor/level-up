<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures\Multipliers;

use LevelUp\Experience\Contracts\Multiplier;

class IsMonthDecember implements Multiplier
{
    public bool $enabled = true;

    public function qualifies(array $data): bool
    {
        return now()->month === 12;
    }

    public function setMultiplier(): int
    {
        return 5;
    }
}
