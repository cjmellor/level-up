<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;

final class MetricNotFoundException extends Exception
{
    public static function forKey(string $key): self
    {
        return new self(message: "No leaderboard metric is registered for key [{$key}]. Register it under 'level-up.leaderboard.metrics'.");
    }

    public static function forBoard(string $name): self
    {
        return new self(message: "The Board [{$name}] does not declare a metric. Every Board under 'level-up.leaderboard.boards' must declare a 'metric' key.");
    }
}
