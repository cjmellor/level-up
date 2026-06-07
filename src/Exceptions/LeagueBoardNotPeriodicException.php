<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;

final class LeagueBoardNotPeriodicException extends Exception
{
    public static function forBoard(string $name): self
    {
        return new self(message: "The league references the Board [{$name}], but it does not declare a period. A League is a competitive cycle — declare a 'period' ('day', 'week', or 'month') on the Board, or bind the league to a periodic Board.");
    }
}
