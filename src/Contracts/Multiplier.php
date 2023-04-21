<?php

namespace LevelUp\Experience\Contracts;

interface Multiplier
{
    public function qualifies(): bool;

    public function setMultiplier(): int;
}
