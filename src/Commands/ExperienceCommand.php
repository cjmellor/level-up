<?php

namespace LevelUp\Experience\Commands;

use Illuminate\Console\Command;

class ExperienceCommand extends Command
{
    public $signature = 'level-up';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
