<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;

final class LeagueDivisionsNotDeclaredException extends Exception
{
    public static function make(): self
    {
        return new self(message: "The league declares no divisions. Declare the ladder bottom to top under 'level-up.leaderboard.league.divisions'.");
    }
}
