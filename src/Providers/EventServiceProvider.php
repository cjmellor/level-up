<?php

namespace LevelUp\Experience\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use LevelUp\Experience\Events\PointsDecreased;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Events\UserLevelledUp;
use LevelUp\Experience\Listeners\PointsDecreasedListener;
use LevelUp\Experience\Listeners\PointsIncreasedListener;
use LevelUp\Experience\Listeners\UserLevelledUpListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PointsIncreased::class => [
            PointsIncreasedListener::class,
        ],
        UserLevelledUp::class => [
            UserLevelledUpListener::class,
        ],
        PointsDecreased::class => [
            PointsDecreasedListener::class,
        ],
    ];

    public function register(): void
    {
        parent::register();
    }

    public function boot(): void
    {
        parent::boot();
    }
}
