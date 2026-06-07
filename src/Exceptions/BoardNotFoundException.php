<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;

final class BoardNotFoundException extends Exception
{
    public static function forName(string $name): self
    {
        return new self(message: "No leaderboard Board is declared for name [{$name}]. Declare it under 'level-up.leaderboard.boards'.");
    }

    public static function forLeague(string $name): self
    {
        return new self(message: "The league references the Board [{$name}], but no such Board is declared. Declare it under 'level-up.leaderboard.boards'.");
    }
}
