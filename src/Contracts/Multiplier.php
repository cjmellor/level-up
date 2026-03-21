<?php

declare(strict_types=1);

namespace LevelUp\Experience\Contracts;

interface Multiplier
{
    public function qualifies(array $data): bool;

    public function setMultiplier(): int;
}
