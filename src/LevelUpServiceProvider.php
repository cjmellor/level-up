<?php

declare(strict_types=1);

namespace LevelUp\Experience;

use LevelUp\Experience\Providers\EventServiceProvider;
use LevelUp\Experience\Services\LeaderboardService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LevelUpServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name(name: 'level-up')
            ->hasConfigFile()
            ->hasMigrations([
                'create_levels_table',
                'create_experiences_table',
                'add_level_relationship_to_users_table',
                'create_experience_audits_table',
                'create_achievements_table',
                'create_achievement_user_pivot_table',
                'create_streak_activities_table',
                'create_streaks_table',
                'create_streak_histories_table',
                'add_streak_freeze_feature_columns_to_streaks_table',
                'remove_level_id_column_from_users_table',
                'alter_experience_audits_type_to_string',
                'create_tiers_table',
                'add_tier_id_to_experiences_table',
                'add_tier_id_to_achievements_table',
                'create_multipliers_table',
                'create_multiplier_scopes_table',
                'add_multipliers_column_to_experience_audits_table',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->register(provider: EventServiceProvider::class);
        $this->app->singleton(abstract: 'leaderboard', concrete: fn (): LeaderboardService => new LeaderboardService());
    }
}
