<?php

namespace LevelUp\Experience\Facades;

use Illuminate\Support\Facades\Facade;

class Leaderboard extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'leaderboard';
    }
}
