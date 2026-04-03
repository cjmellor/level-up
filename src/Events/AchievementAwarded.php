<?php

declare(strict_types=1);

namespace LevelUp\Experience\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use LevelUp\Experience\Models\Achievement;

class AchievementAwarded
{
    use Dispatchable;

    public function __construct(
        public readonly Achievement $achievement,
        public readonly Model $user,
    ) {}
}
