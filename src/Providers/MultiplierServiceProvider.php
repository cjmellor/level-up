<?php

namespace LevelUp\Experience\Providers;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use LevelUp\Experience\Services\MultiplierService;
use ReflectionClass;

class MultiplierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            abstract: MultiplierService::class,
            concrete: fn (Application $app, array $params): MultiplierService => new MultiplierService(
                multipliers: collect(value: File::allFiles(config(key: 'level-up.multiplier.path')))
                    ->map(callback: fn ($file) => new ReflectionClass(sprintf('%s%s', config(key: 'level-up.multiplier.namespace'), str($file->getFilename())->replace(search: '.php', replace: ''))))
                    ->filter(callback: fn (ReflectionClass $class): bool => $class->getProperty(name: 'enabled')->getValue($class->newInstance()) === true)
                    ->map(callback: fn (ReflectionClass $class) => $app->make($class->getName())),
                data: $params['data'] ?? []
            )
        );
    }

    public function boot(): void
    {
    }
}
