<?php

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use LevelUp\Experience\Models\Achievement;

class AchievementProgressionIncreased
{
    use Dispatchable;

    public function __construct(
        public Achievement $achievement,
        public Model $user,
        public int $amount,
    ) {}
}
