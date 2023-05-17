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
        collect(glob(database_path(path: 'migrations/*.php.stub')))
            ->map(callback: fn (string $fileName): array|string => str_replace(search: '.stub', replace: '', subject: basename($fileName)))
            ->toArray();

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
