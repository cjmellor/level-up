<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;
use LevelUp\Experience\Contracts\RankingMetric;

final class MetricDisabledException extends Exception
{
    public static function forMetric(RankingMetric $metric): self
    {
        return new self(message: "The [{$metric->key()}] leaderboard metric is disabled because its underlying feature is turned off.");
    }
}
