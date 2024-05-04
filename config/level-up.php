<?php

return [
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
    | Level Up Tables
    |--------------------------------------------------------------------------
    |
    | This value is the name of the tables that will be used to store level up data.
    | You can change the table names to fit your application.
    |
    | The default table names are set to the Laravel conventions.
    |
    | It is recommended to keep the table names as they are, only change them if
    | you know what you are doing.
    |
    | 'typical_table_name' => 'new_table_name',
    |
     */
    'tables' => [
        'achievement_user' => 'achievement_user', // pivot table
        'experiences' => 'experiences',
        'experience_audits' => 'experience_audits',
        'achievements' => 'achievements',
        'levels' => 'levels',
        'streaks' => 'streaks',
        'streak_histories' => 'streak_histories',
        'streak_activities' => 'streak_activities',
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
