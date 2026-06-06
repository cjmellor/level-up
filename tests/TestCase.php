<?php

namespace LevelUp\Experience\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        config()->set('level-up.entities.id_type', env('LEVELUP_TEST_KEY_TYPE', 'bigint'));

        $this->defineUserConfig();

        Schema::create('users', function (Blueprint $table): void {
            $this->createUserIdColumn($table);
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function defineDatabaseMigrations(): void
    {
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
            'create_multiplier_user_table',
            'create_multiplier_tier_table',
            'migrate_multiplier_scopes_to_typed_pivots',
            'add_multipliers_column_to_experience_audits_table',
            'create_challenges_table',
            'create_challenge_user_table',
            'create_leaderboard_snapshots_table',
        ];

        foreach ($migrations as $migrationFile) {
            $migration = include __DIR__."/../database/migrations/$migrationFile.php.stub";

            $migration->up();
        }
    }

    protected function defineUserConfig(): void
    {
        config()->set('level-up.user.model', \LevelUp\Experience\Tests\Fixtures\User::class);
    }

    protected function createUserIdColumn(Blueprint $table): void
    {
        match (config('level-up.user.foreign_key_type', 'bigint')) {
            'bigint' => $table->id(),
            'uuid' => $table->uuid('id')->primary(),
            'ulid' => $table->ulid('id')->primary(),
            default => $table->id(),
        };
    }
}
