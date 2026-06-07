<?php

declare(strict_types=1);

namespace LevelUp\Experience\Commands;

use Illuminate\Console\Command;
use LevelUp\Experience\Services\LeagueService;

class LeagueRolloverCommand extends Command
{
    protected $signature = 'level-up:league-rollover';

    protected $description = 'Roll over closed league periods: promote and relegate each cohort\'s finishers and stamp the cohorts as rolled over.';

    public function handle(LeagueService $league): int
    {
        $league->rollover();

        return self::SUCCESS;
    }
}
