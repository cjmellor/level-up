<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Streak;

class StreakIncreased
{
    use Dispatchable;

    public function __construct(
        public readonly Model $user,
        public readonly Activity $activity,
        public readonly Streak $streak,
    ) {}
}
