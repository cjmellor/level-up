<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;

final class LevelExistsException extends Exception
{
    public static function handle(int $levelNumber): static
    {
        return new self(message: sprintf('The level with number "%d" already exists.', $levelNumber));
    }
}
