<?php

declare(strict_types=1);

namespace LevelUp\Experience\Commands;

use Illuminate\Console\Command;
use LevelUp\Experience\Services\LeagueService;

#[\Illuminate\Console\Attributes\Description('Roll over closed league periods: promote and relegate each cohort\'s finishers and stamp the cohorts as rolled over.')]
#[\Illuminate\Console\Attributes\Signature('level-up:league-rollover')]
class LeagueRolloverCommand extends Command
{
    public function handle(LeagueService $league): int
    {
        $league->rollover();

        return self::SUCCESS;
    }
}
