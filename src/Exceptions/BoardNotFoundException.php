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
}
