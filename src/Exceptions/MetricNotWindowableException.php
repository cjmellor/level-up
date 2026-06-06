<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;
use LevelUp\Experience\Contracts\RankingMetric;

final class MetricNotWindowableException extends Exception
{
    public static function forMetric(RankingMetric $metric): self
    {
        return new self(message: "The [{$metric->key()}] leaderboard metric ranks by current state and does not support time Periods. Remove the period()/since() call, or rank by a Windowable metric such as [xp].");
    }
}
