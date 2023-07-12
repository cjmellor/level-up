<?php

namespace LevelUp\Experience\Tests\Fixtures\Multipliers;

use LevelUp\Experience\Contracts\Multiplier;

class HasExternalData implements Multiplier
{
    public bool $enabled = true;

    public function qualifies(array $data): bool
    {
        return isset($data['event_id']) && $data['event_id'] === 2;
    }

    public function setMultiplier(): int
    {
        return 5;
    }
}
