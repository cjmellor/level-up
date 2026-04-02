<?php

declare(strict_types=1);

use App\Models\User;
use LevelUp\Experience\Models\Achievement;
use LevelUp\Experience\Models\Activity;
use LevelUp\Experience\Models\Challenge;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\ExperienceAudit;
use LevelUp\Experience\Models\Level;
use LevelUp\Experience\Models\Pivots\AchievementUser;
use LevelUp\Experience\Models\Pivots\ChallengeUser;
use LevelUp\Experience\Models\Streak;
use LevelUp\Experience\Models\StreakHistory;
use LevelUp\Experience\Models\Tier;

return [

    'models' => [
        'achievement' => Achievement::class,
        'activity' => Activity::class,
        'experience' => Experience::class,
        'experience_audit' => ExperienceAudit::class,
        'level' => Level::class,
        'streak' => Streak::class,
        'streak_history' => StreakHistory::class,
        'achievement_user' => AchievementUser::class,
        'tier' => Tier::class,
        'challenge' => Challenge::class,
        'challenge_user' => ChallengeUser::class,
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
        | Tier-based multipliers. Map tier names to multiplier values.
        | When set, users in that tier automatically receive the
        | multiplier on all points earned.
        |
        | Example: ['Bronze' => 1, 'Silver' => 1.5, 'Gold' => 2]
        */
        'multipliers' => [],

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
