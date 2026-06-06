<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;

final class TierExistsException extends Exception
{
    public static function handle(string $tierName): static
    {
        return new self(message: sprintf('The tier with name "%s" already exists.', $tierName));
    }
}
