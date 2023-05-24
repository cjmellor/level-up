<?php

namespace LevelUp\Experience;

use LevelUp\Experience\Providers\EventServiceProvider;
use LevelUp\Experience\Providers\MultiplierServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LevelUpServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('level-up')
            ->hasConfigFile()
            ->hasMigrations([
                'create_levels_table',
                'create_experiences_table',
                'add_level_relationship_to_users_table',
                'create_experience_audits_table',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->register(EventServiceProvider::class);
        $this->app->register(MultiplierServiceProvider::class);
    }
}
