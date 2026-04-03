<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;

class TierExistsException extends Exception
{
    public static function handle(string $tierName): static
    {
        return new static(message: sprintf('The tier with name "%s" already exists.', $tierName));
    }
}
