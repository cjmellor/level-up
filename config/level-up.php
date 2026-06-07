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
        'leaderboard_snapshot' => LevelUp\Experience\Models\LeaderboardSnapshot::class,
        'division' => LevelUp\Experience\Models\Division::class,
        'cohort' => LevelUp\Experience\Models\Cohort::class,
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
        'leaderboard_snapshots' => 'leaderboard_snapshots',
        'divisions' => 'divisions',
        'cohorts' => 'cohorts',
        'cohort_user' => 'cohort_user',
    ],

    /*
    |-----------------------------------------------------------------------
    | Leaderboard
    |-----------------------------------------------------------------------
    |
    | Configure the leaderboard. 'metrics' maps registry keys to
    | RankingMetric classes — register custom metrics by adding entries.
    | 'default_metric' is used when no metric is specified.
    |
    | 'week_starts_on' sets the boundary of Period::Week as a Carbon
    | day-of-week number: 0 (Sunday) through 6 (Saturday). Defaults to
    | Monday. 'timezone' controls which timezone period boundaries
    | (start of day/week/month) are computed in; null uses the
    | application timezone.
    |
    | 'boards' declares named Boards — leaderboards the package can
    | track over time. Each entry maps a board name to a 'metric'
    | (required registry key), an optional 'period' ('day', 'week',
    | or 'month'), an optional 'tier' (a tier name), and an optional
    | 'track_top' (the tracked depth — how many top entries the
    | snapshot run stores and events, default 100). For example:
    | 'weekly-xp' => ['metric' => 'xp', 'period' => 'week'].
    |
    | 'snapshots.retention_days' controls how long snapshot runs are
    | kept; the level-up:snapshot-boards command prunes older runs.
    |
    | 'league' declares the League — a competitive cycle built on one
    | periodic Board. 'board' names the Board users compete on (it must
    | be declared under 'boards' and have a 'period'); leave it null and
    | the league machinery stays dormant. 'cohort_size' caps how many
    | users share a Cohort (default 30). 'divisions' is the ladder,
    | ordered bottom to top; each entry maps a Division name to its
    | 'promote' and 'relegate' counts, which are read by the
    | level-up:league-rollover command at period close. For example:
    |
    | 'league' => [
    |     'board' => 'weekly-xp',
    |     'cohort_size' => 30,
    |     'divisions' => [
    |         'Bronze' => ['promote' => 10, 'relegate' => 0],
    |         'Silver' => ['promote' => 7, 'relegate' => 5],
    |         'Gold' => ['promote' => 0, 'relegate' => 5],
    |     ],
    | ],
    |
    */
    'leaderboard' => [
        'default_metric' => 'xp',
        'metrics' => [
            'xp' => LevelUp\Experience\Metrics\ExperienceMetric::class,
            'level' => LevelUp\Experience\Metrics\LevelMetric::class,
            'streak' => LevelUp\Experience\Metrics\StreakMetric::class,
            'achievements' => LevelUp\Experience\Metrics\AchievementMetric::class,
            'challenges' => LevelUp\Experience\Metrics\ChallengeMetric::class,
        ],
        'boards' => [],
        'snapshots' => [
            'retention_days' => 30,
        ],
        'league' => [
            'board' => null,
            'cohort_size' => 30,
            'divisions' => [],
        ],
        'week_starts_on' => Carbon\CarbonInterface::MONDAY,
        'timezone' => null,
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
    | Set the audit configuration. Auditing records every point transaction
    | in the experience_audits ledger and is required for time-windowed
    | (periodic) leaderboards. Enabled by default since v3.
    |
    */
    'audit' => [
        'enabled' => env(key: 'AUDIT_POINTS', default: true),
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
