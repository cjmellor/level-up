<?php

namespace LevelUp\Experience\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use LevelUp\Experience\LevelUpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * @property \LevelUp\Experience\Tests\Fixtures\User $user
 * @property \LevelUp\Experience\Models\Challenge $challenge
 * @property \LevelUp\Experience\Models\Activity $activity
 */
class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'LevelUp\\Experience\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            LevelUpServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('level-up.user.model', \LevelUp\Experience\Tests\Fixtures\User::class);

        \Illuminate\Support\Facades\Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        $migrations = [
            'create_levels_table',
            'create_experiences_table',
            'create_experience_audits_table',
            'create_achievements_table',
            'create_achievement_user_pivot_table',
            'create_streaks_table',
            'create_streak_histories_table',
            'create_streak_activities_table',
            'add_streak_freeze_feature_columns_to_streaks_table',
            'add_level_relationship_to_users_table',
            'remove_level_id_column_from_users_table',
            'alter_experience_audits_type_to_string',
            'create_tiers_table',
            'add_tier_id_to_experiences_table',
            'add_tier_id_to_achievements_table',
            'create_multipliers_table',
            'create_multiplier_scopes_table',
            'add_multipliers_column_to_experience_audits_table',
            'create_challenges_table',
            'create_challenge_user_table',
        ];

        foreach ($migrations as $migrationFile) {
            $migration = include __DIR__."/../database/migrations/$migrationFile.php.stub";

            $migration->up();
        }
    }
}
