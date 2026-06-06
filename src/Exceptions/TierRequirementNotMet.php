<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;

final class TierRequirementNotMet extends Exception
{
    public static function handle(string $tierName): static
    {
        return new self(message: sprintf('User does not meet the required tier "%s" for this achievement.', $tierName));
    }
}
