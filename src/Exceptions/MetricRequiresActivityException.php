<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;
use LevelUp\Experience\Contracts\RankingMetric;

final class MetricRequiresActivityException extends Exception
{
    public static function forMetric(RankingMetric $metric): self
    {
        return new self(message: "The [{$metric->key()}] leaderboard metric requires an Activity. Construct it with one and pass the instance to Leaderboard::by().");
    }
}
