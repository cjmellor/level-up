<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;

class LevelExistsException extends Exception
{
    public static function handle(int $levelNumber): static
    {
        return new static(message: sprintf('The level with number "%d" already exists.', $levelNumber));
    }
}
