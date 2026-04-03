<?php

declare(strict_types=1);

namespace LevelUp\Experience\Tests\Fixtures;

class Level extends \LevelUp\Experience\Models\Level
{
    public function extra_function(): string
    {
        return 'extra_function';
    }
}
