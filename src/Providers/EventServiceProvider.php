<?php

declare(strict_types=1);

namespace LevelUp\Experience\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use LevelUp\Experience\Events\AchievementAwarded;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\StreakIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Events\UserTierUpdated;
use LevelUp\Experience\Listeners\ChallengeProgressListener;
use LevelUp\Experience\Listeners\PointsDecreasedListener;
use LevelUp\Experience\Listeners\PointsIncreasedListener;
use LevelUp\Experience\Listeners\UserLevelledUpListener;
use LevelUp\Experience\Listeners\UserTierUpdatedListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PointsDecreased::class => [
            PointsDecreasedListener::class,
        ],
        PointsIncreased::class => [
            PointsIncreasedListener::class,
            ChallengeProgressListener::class,
        ],
        UserLevelledUp::class => [
            UserLevelledUpListener::class,
            ChallengeProgressListener::class,
        ],
        UserTierUpdated::class => [
            UserTierUpdatedListener::class,
            ChallengeProgressListener::class,
        ],
        AchievementAwarded::class => [
            ChallengeProgressListener::class,
        ],
        StreakIncreased::class => [
            ChallengeProgressListener::class,
        ],
    ];
}
