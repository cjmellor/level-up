<?php

namespace LevelUp\Experience;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LevelUpServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('level-up')
            ->hasConfigFile()
            ->hasMigrations(['create_experiences_table', 'create_levels_table']);
    }

    public function register(): void
    {
        parent::register();

        $this->app->register(EventServiceProvider::class);
    }
}
