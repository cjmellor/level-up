<?php

declare(strict_types=1);

namespace LevelUp\Experience;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ForeignIdColumnDefinition;
use InvalidArgumentException;
use LevelUp\Experience\Providers\EventServiceProvider;
use LevelUp\Experience\Services\LeaderboardService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LevelUpServiceProvider extends PackageServiceProvider
{
    public static function resolveTables(string $prefix, array $overrides): array
    {
        $defaults = [
            'experiences' => 'experiences',
            'experience_audits' => 'experience_audits',
            'levels' => 'levels',
            'achievements' => 'achievements',
            'achievement_user' => 'achievement_user',
            'streaks' => 'streaks',
            'streak_histories' => 'streak_histories',
            'streak_activities' => 'streak_activities',
            'tiers' => 'tiers',
            'multipliers' => 'multipliers',
            'multiplier_scopes' => 'multiplier_scopes',
            'challenges' => 'challenges',
            'challenge_user' => 'challenge_user',
        ];

        $resolved = [];

        foreach ($defaults as $key => $default) {
            $override = $overrides[$key] ?? null;
            $overrideIsExplicit = is_string($override) && $override !== '' && $override !== $default;

            $resolved[$key] = $overrideIsExplicit ? $override : $prefix.$default;
        }

        return $resolved;
    }

    public function bootingPackage(): void
    {
        $this->registerEntityKeyMacros();
    }

    public function packageBooted(): void
    {
        config()->set('level-up.tables', static::resolveTables(
            prefix: (string) config('level-up.table_prefix', ''),
            overrides: (array) config('level-up.tables', []),
        ));
    }

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
                'create_challenges_table',
                'create_challenge_user_table',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->register(provider: EventServiceProvider::class);
        $this->app->singleton(abstract: 'leaderboard', concrete: fn (): LeaderboardService => new LeaderboardService());
    }

    protected function registerEntityKeyMacros(): void
    {
        $resolveIdType = static function (): string {
            $idType = config('level-up.entities.id_type', 'bigint');

            return match ($idType) {
                'bigint', 'uuid', 'ulid' => $idType,
                default => throw new InvalidArgumentException(
                    "Unknown level-up.entities.id_type [{$idType}]. Expected 'bigint', 'uuid', or 'ulid'."
                ),
            };
        };

        if (! Blueprint::hasMacro('entityId')) {
            Blueprint::macro('entityId', function (string $column = 'id') use ($resolveIdType): void {
                /** @var Blueprint $this */
                match ($resolveIdType()) {
                    'bigint' => $this->id($column),
                    'uuid' => $this->uuid($column)->primary(),
                    'ulid' => $this->ulid($column)->primary(),
                };
            });
        }

        if (! Blueprint::hasMacro('entityForeignId')) {
            Blueprint::macro('entityForeignId', function (string $column) use ($resolveIdType): ForeignIdColumnDefinition {
                /** @var Blueprint $this */
                return match ($resolveIdType()) {
                    'bigint' => $this->foreignId($column),
                    'uuid' => $this->foreignUuid($column),
                    'ulid' => $this->foreignUlid($column),
                };
            });
        }
    }
}
