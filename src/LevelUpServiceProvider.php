<?php

namespace LevelUp\Experience;

use LevelUp\Experience\Commands\MakeMultiplierCommand;
use LevelUp\Experience\Providers\EventServiceProvider;
use LevelUp\Experience\Providers\MultiplierServiceProvider;
use LevelUp\Experience\Services\LeaderboardService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LevelUpServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name(name: 'level-up')
            ->hasCommand(commandClassName: MakeMultiplierCommand::class)
            ->hasConfigFile()
            ->hasMigrations([
                'create_levels_table',
                'create_experiences_table',
                'add_level_relationship_to_users_table',
                'create_experience_audits_table',
                'create_achievements_table',
                'create_achievement_user_pivot_table',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->register(provider: EventServiceProvider::class);
        $this->app->singleton(abstract: 'leaderboard', concrete: fn () => new LeaderboardService());
        $this->app->register(provider: MultiplierServiceProvider::class);
    }
}
