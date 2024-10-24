<?php

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
        'model' => App\Models\User::class,
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
    | Multiplier Paths
    |-----------------------------------------------------------------------
    |
    | Set the path and namespace for the Multiplier classes.
    |
    */
    'multiplier' => [
        'enabled' => env(key: 'MULTIPLIER_ENABLED', default: true),
        'path' => env(key: 'MULTIPLIER_PATH', default: app_path(path: 'Multipliers')),
        'namespace' => env(key: 'MULTIPLIER_NAMESPACE', default: 'App\\Multipliers\\'),
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
];
