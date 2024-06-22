<?php

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Streak;

class StreakBroken
{
    use Dispatchable;

    public function __construct(
        public Model $user,
        public Activity $activity,
        public Streak $streak,
    ) {}
}
