<?php

declare(strict_types=1);

namespace LevelUp\Experience\Exceptions;

use Exception;
use LevelUp\Experience\Contracts\RankingMetric;

final class MetricRequiresAuditingException extends Exception
{
    public static function forMetric(RankingMetric $metric): self
    {
        return new self(message: "The [{$metric->key()}] leaderboard metric sources periodic Scores from the experience audit ledger, but auditing is disabled. Enable [level-up.audit.enabled] to use time Periods.");
    }
}
