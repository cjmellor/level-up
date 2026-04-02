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
        'multiplier_scope' => LevelUp\Experience\Models\MultiplierScope::class,
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
     */
    'user' => [
        'foreign_key' => 'user_id',
        'model' => User::class,
        'users_table' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Experience Table
    |--------------------------------------------------------------------------
    |
    | This value is the name of the table that will be used to store experience data.
    |
     */
    'table' => 'experiences',

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
