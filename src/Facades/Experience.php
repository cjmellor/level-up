<?php

namespace LevelUp\Experience\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \LevelUp\Experience\Experience
 */
class Experience extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LevelUp\Experience\Experience::class;
    }
}
