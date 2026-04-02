<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures\Challenges;

class NotACondition
{
    public function check(): bool
    {
        return true;
    }
}
