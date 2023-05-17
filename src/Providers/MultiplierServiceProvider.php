<?php

namespace LevelUp\Experience\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use LevelUp\Experience\Services\MultiplierService;
use ReflectionClass;

class MultiplierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            abstract: MultiplierService::class,
            concrete: fn ($app, array $params): MultiplierService => new MultiplierService(
                multipliers: collect(value: File::allFiles(config(key: 'level-up.multiplier.path')))
                    ->map(callback: fn ($file): ReflectionClass => new ReflectionClass(sprintf('%s%s', config(key: 'level-up.multiplier.namespace'), Str::of($file->getFilename())->replace(search: '.php', replace: ''))))
                    ->filter(callback: fn (ReflectionClass $class): bool => $class->getProperty('enabled')->getValue($class->newInstance()) === true)
                    ->map(callback: fn (ReflectionClass $class) => $app->make($class->getName())),
                data: $params['data'] ?? []
            )
        );
    }

    public function boot(): void
    {
    }
}
