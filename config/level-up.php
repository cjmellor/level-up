<?php

declare(strict_types=1);

return [

    'models' => [
        'achievement' => LevelUp\Experience\Models\Achievement::class,
        'activity' => LevelUp\Experience\Models\Activity::class,
        'experience' => LevelUp\Experience\Models\Experience::class,
        'experience_audit' => LevelUp\Experience\Models\ExperienceAudit::class,
        'level' => LevelUp\Experience\Models\Level::class,
        'streak' => LevelUp\Experience\Models\Streak::class,
        'streak_history' => LevelUp\Experience\Models\StreakHistory::class,
        'achievement_user' => LevelUp\Experience\Models\Pivots\AchievementUser::class,
        'tier' => LevelUp\Experience\Models\Tier::class,
        'multiplier' => LevelUp\Experience\Models\Multiplier::class,
        'multiplier_user' => LevelUp\Experience\Models\Pivots\MultiplierUser::class,
        'multiplier_tier' => LevelUp\Experience\Models\Pivots\MultiplierTier::class,
        'challenge' => LevelUp\Experience\Models\Challenge::class,
        'challenge_user' => LevelUp\Experience\Models\Pivots\ChallengeUser::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | User Foreign Key
    |--------------------------------------------------------------------------
    |
    | This value is the foreign key that will be used to relate the Experience model to the User model.
    |
    | 'foreign_key_type' controls the DB column type used for the user FK on
    | every package table. Set to 'uuid' or 'ulid' if your host User model
    | uses HasUuids / HasUlids. Leave as 'bigint' for standard auto-increment
    | user IDs. This only affects fresh migrations; existing installs keep
    | whichever column type they originally migrated with.
    |
     */
    'user' => [
        'foreign_key' => 'user_id',
        'foreign_key_type' => 'bigint',
        'model' => App\Models\User::class,
        'users_table' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Entities
    |--------------------------------------------------------------------------
    |
    | 'id_type' controls the primary key column type used for the package's
    | own tables (experiences, levels, achievements, etc.) and every internal
    | foreign key between them. Set to 'uuid' or 'ulid' if you want package
    | IDs to be opaque (e.g., for safe exposure on a public API surface).
    | Leave as 'bigint' for standard auto-increment IDs. Only affects fresh
    | migrations; existing installs keep whichever column type they already
    | migrated with. See the README "Customizing Identifiers" section for
    | guidance on switching an existing install.
    |
    */
    'entities' => [
        'id_type' => 'bigint',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    |
    | Prepended to every default package table name. Leave empty for no prefix.
    | Per-table overrides in 'tables' below are taken verbatim and are NOT
    | prefixed.
    |
    */
    'table_prefix' => env('LEVEL_UP_TABLE_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | The table name used for each of the package's models. Leave a value
    | equal to the default to apply table_prefix above; set it to any other
    | string to override that table's name exactly (no prefix applied).
    |
    */
    'tables' => [
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
        'multiplier_user' => 'multiplier_user',
        'multiplier_tier' => 'multiplier_tier',
        'challenges' => 'challenges',
        'challenge_user' => 'challenge_user',
    ],

    /*
    |-----------------------------------------------------------------------
    | Starting Level
    |-----------------------------------------------------------------------
    |
    | The level that a User starts with.
    |
    */
    'starting_level' => 1,

    /*
    |-----------------------------------------------------------------------
    | Multipliers
    |-----------------------------------------------------------------------
    |
    | Configure the multiplier system. Multipliers are managed via the
    | database and can be scoped to specific users or tiers.
    |
    */
    'multiplier' => [
        'enabled' => env(key: 'MULTIPLIER_ENABLED', default: true),
        'stack_strategy' => env(key: 'MULTIPLIER_STACK', default: 'compound'),
        // 'compound'  — multipliers multiply each other: 2 × 5 = 10x
        // 'additive'  — multipliers sum:              2 + 5 = 7x
        // 'highest'   — only the largest applies:  max(2, 5) = 5x
    ],

    /*
    |-----------------------------------------------------------------------
    | Level Cap
    |-----------------------------------------------------------------------
    |
    | Set the maximum level a User can reach.
    |
    */
    'level_cap' => [
        'enabled' => env(key: 'LEVEL_CAP_ENABLED', default: true),
        'level' => env(key: 'LEVEL_CAP', default: 100),
        'points_continue' => env(key: 'LEVEL_CAP_POINTS_CONTINUE', default: true),
    ],

    /*
    | -------------------------------------------------------------------------
    | Audit
    | -------------------------------------------------------------------------
    |
    | Set the audit configuration.
    |
    */
    'audit' => [
        'enabled' => env(key: 'AUDIT_POINTS', default: false),
    ],

    /*
    | -------------------------------------------------------------------------
    | Record streak history
    | -------------------------------------------------------------------------
    |
    | Set the streak history configuration.
    |
    */
    'archive_streak_history' => [
        'enabled' => env(key: 'ARCHIVE_STREAK_HISTORY_ENABLED', default: true),
    ],

    /*
     | -------------------------------------------------------------------------
     | Default Streak Freeze Time
     | -------------------------------------------------------------------------
     |
     | Set the default time in days that a streak will be frozen for.
     |
     */
    'freeze_duration' => env(key: 'STREAK_FREEZE_DURATION', default: 1),

    /*
    | -------------------------------------------------------------------------
    | Tiers
    | -------------------------------------------------------------------------
    |
    | Configure the tier system. Tiers provide named status brackets
    | (e.g. Bronze, Silver, Gold) based on experience points.
    |
    */
    'tiers' => [
        'enabled' => env(key: 'TIERS_ENABLED', default: true),
        'demotion' => env(key: 'TIER_DEMOTION', default: false),

        /*
        | Tier-based streak freeze duration (in days). Map tier names
        | to the number of days a streak can be frozen.
        | Falls back to the global 'freeze_duration' if not set.
        |
        | Example: ['Bronze' => 1, 'Silver' => 2, 'Gold' => 3]
        */
        'streak_freeze_days' => [],
    ],

    /*
    | -------------------------------------------------------------------------
    | Challenges
    | -------------------------------------------------------------------------
    |
    | Configure the challenges system. Challenges are multi-condition goals
    | that users can enroll in and complete for rewards.
    |
    */
    'challenges' => [
        'enabled' => env(key: 'CHALLENGES_ENABLED', default: true),
    ],
];
