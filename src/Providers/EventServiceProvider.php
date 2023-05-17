<?php

namespace LevelUp\Experience\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use LevelUp\Experience\Events\PointsIncreased;
use LevelUp\Experience\Listeners\PointsIncreasedListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PointsIncreased::class => [
            PointsIncreasedListener::class,
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
