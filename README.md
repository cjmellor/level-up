[![Latest Version on Packagist](https://img.shields.io/packagist/v/cjmellor/level-up?color=rgb%2856%20189%20248%29&label=release&style=for-the-badge)](https://packagist.org/packages/cjmellor/level-up)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/cjmellor/level-up/run-tests.yml?branch=main&label=tests&style=for-the-badge&color=rgb%28134%20239%20128%29)](https://github.com/cjmellor/level-up/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cjmellor/level-up.svg?color=rgb%28249%20115%2022%29&style=for-the-badge)](https://packagist.org/packages/cjmellor/level-up)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/cjmellor/level-up/php?color=rgb%28165%20180%20252%29&logo=php&logoColor=rgb%28165%20180%20252%29&style=for-the-badge)
![Laravel Version](https://img.shields.io/badge/laravel-^12|^13-rgb(235%2068%2050)?style=for-the-badge&logo=laravel)

This package allows users to gain experience points (XP) and progress through levels by performing actions on your site. It can provide a simple way to track user progress and implement gamification elements into your application

![Banner](https://banners.beyondco.de/Level%20Up.png?theme=dark&packageManager=composer+require&packageName=cjmellor%2Flevel-up&pattern=ticTacToe&style=style_1&description=Enable+gamification+via+XP%2C+levels%2C+leaderboards%2C+achievements%2C+and+dynamic+multipliers&md=1&showWatermark=0&fontSize=100px&images=puzzle&widths=auto)

# Installation

You can install the package via composer:

```
composer require cjmellor/level-up
```

You can publish and run the migrations with:

```
php artisan vendor:publish --tag="level-up-migrations"
php artisan migrate
```

You can publish the config file with:

```
php artisan vendor:publish --tag="level-up-config"
```

This is the contents of the published config file:

```php
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
```

# Customizing Table Names

If you're installing into an app that already has tables called `experiences`, `levels`, `tiers`, `multipliers`, `challenges`, or any of the package's other defaults, you can rename them via config — no need to patch published migrations.

**Option 1 — Apply a single prefix to every package table:**

Set an env var (no config publish required):

```dotenv
LEVEL_UP_TABLE_PREFIX=levelup_
```

…or publish the config and edit the prefix line:

```bash
php artisan vendor:publish --tag=level-up-config
```

```php
// config/level-up.php
'table_prefix' => 'levelup_',
```

All package tables now use the prefix: `levelup_experiences`, `levelup_levels`, `levelup_tiers`, `levelup_multipliers`, `levelup_challenges`, and so on.

**Option 2 — Rename specific tables:**

Edit the `tables` array in the published config:

```php
'tables' => [
    'experiences' => 'xp_log',         // renamed
    'levels'      => 'user_tiers',     // renamed
    'tiers'       => 'rank_brackets',  // renamed
    // leave the rest at their defaults
],
```

**Combining both:** any value left equal to the default receives the `table_prefix`; any value you change is taken verbatim and the prefix is NOT applied. This lets you prefix everything but override one or two outliers:

```php
'table_prefix' => 'lvl_',
'tables' => [
    'levels' => 'xp_levels',  // → 'xp_levels' (NO prefix)
    // others stay default → all become 'lvl_<default>'
],
```

> **Upgrading from v1.x or earlier v2:** the previous top-level `'table'` config key (used to override only the experiences table) still works as a fallback. New installations should prefer `'tables.experiences'` instead.

# Usage

## 💯 Experience Points (XP)

Add the `GiveExperience` trait to your `User` model.

```php
use LevelUp\Experience\Concerns\GiveExperience;

class User extends Model
{
    use GiveExperience;

    // ...
}
```

**Give XP points to a User**

```php
$user->addPoints(10);
```

A new record will be added to the `experiences` table which stores the Users’ points. If a record already exists, it will be updated instead. All new records will be given a `level_id` of `1`.

> [!NOTE]
> If you didn't set up your Level structure yet, a default Level of `1` will be added to get you started.

**Deduct XP points from a User**

```php
$user->deductPoints(10);
```

> [!NOTE]
> Both `deductPoints` and `setPoints` will throw an exception if the User has no experience record.

**Set XP points to a User**

For an event where you just want to directly add a certain number of points to a User. Points can only be ***set*** if the User has an Experience Model.

```php
$user->setPoints(10);
```

**Retrieve a Users’ points**

```php
$user->getPoints();
```

### Multipliers

Multipliers modify the experience points a User earns. They are managed via the database, so you can create, schedule, and toggle multipliers at runtime — no code deployments needed. This makes them ideal for building admin panels or managing promotional events.

**Create a Multiplier**

```php
use LevelUp\Experience\Models\Multiplier;

Multiplier::create([
    ‘name’ => ‘WrestleMania Weekend’,
    ‘multiplier’ => 5,
    ‘is_active’ => true,
    ‘starts_at’ => ‘2026-04-05 00:00:00’,
    ‘expires_at’ => ‘2026-04-07 23:59:59’,
]);
```

Active multipliers are automatically applied when a User earns points via `addPoints()`. Time-based multipliers activate and deactivate based on `starts_at` and `expires_at`. Non-time-based multipliers (like "Man UTD Win the League") are toggled via `is_active`.

**Scope Multipliers to Users or Tiers**

By default, a multiplier applies to all Users. You can restrict it to specific Users or Tiers:

```php
// Scope to one or more tiers (variadic, idempotent)
$multiplier->scopeToTier($premiumTier, $diamondTier);

// Scope to one or more users (variadic, idempotent)
$multiplier->scopeToUser($user);

// Reverse either with unscope helpers
$multiplier->unscopeFromTier($diamondTier);
$multiplier->unscopeFromUser($user);

// Check whether a multiplier has any scopes
$multiplier->isGlobal();  // true if no users or tiers attached
```

If a multiplier has no scopes, it applies to everyone. If it has scopes, it only applies to Users who match (either directly or via their Tier).

The `tiers()` and `users()` relations are standard `belongsToMany` — you can drop down to `attach()` / `detach()` / `sync()` for full control, but `scopeToUser` / `scopeToTier` are the recommended convenience methods because they're idempotent (they use `syncWithoutDetaching` under the hood, so calling them twice with the same model doesn't create duplicates).

**Query Multipliers**

```php
Multiplier::active()->get();               // Currently active
Multiplier::active()->forUser($user)->get(); // Active for a specific user
Multiplier::scheduled()->get();             // Armed but starts_at is in the future
Multiplier::expired()->get();               // Past their expires_at
```

**Stacking Strategy**

When multiple multipliers are active, you can control how they combine via the `stack_strategy` config:

```
MULTIPLIER_STACK=compound   # 2x × 5x = 10x (default)
MULTIPLIER_STACK=additive   # 2x + 5x = 7x
MULTIPLIER_STACK=highest    # max(2x, 5x) = 5x
```

**Inline Multipliers**

You can also pass a one-off multiplier directly when adding points. This participates in the configured stacking strategy alongside any active DB multipliers:

```php
$user->addPoints(
    amount: 10,
    multiplier: 2
);
```

### Events

**PointsIncreased** - When points are added.

```php
public int $pointsAdded,
public int $totalPoints,
public string $type,
public ?string $reason,
public Model $user,
public ?array $multipliers, // Applied multipliers (if any)
```

**MultiplierApplied** - When multipliers are applied during point addition.

```php
public Model $user,
public Collection $multipliers,   // DB multiplier models that were applied
public int $originalAmount,       // Points before multipliers
public int $finalAmount,          // Points after multipliers
public string $strategy,          // 'compound', 'additive', or 'highest'
```

**PointsDecreased** - When points are decreased.

```php
public int $pointsDecreasedBy,
public int $totalPoints,
public ?string $reason,
public Model $user,
```

## ⬆️ Levelling

> [!NOTE]
> If you add points before setting up your levelling structure, a default Level of `1` will be added to get you started.

### Set up your levelling structure

The package has a handy facade to help you create your levels.

```php
Level::add(
    ['level' => 1, 'next_level_experience' => null],
    ['level' => 2, 'next_level_experience' => 100],
    ['level' => 3, 'next_level_experience' => 250],
);
```

**Level 1** should always be `null` for the `next_level_experience` as it is the default starting point.

As soon as a User gains the correct number of points listed for the next level, they will level-up.

> [!TIP]
> a User gains 50 points, they’ll still be on Level 1, but gets another 50 points, so the User will now move onto Level 2

**See how many points until the next level**

```php
$user->nextLevelAt();
```

**Get the Users’ current Level**

```php
$user->getLevel();
```

### Level Cap

A level cap sets the maximum level that a user can reach. Once a user reaches the level cap, they will not be able to gain any more levels, even if they continue to earn experience points. The level cap is enabled by default and capped to level `100`. These
options can be changed in the packages config file at `config/level-up.php` or by adding them to your `.env` file.

```
LEVEL_CAP_ENABLED=
LEVEL_CAP=
LEVEL_CAP_POINTS_CONTINUE
```

By default, even when a user hits the level cap, they will continue to earn experience points. To freeze this, so points do not increase once the cap is hit, turn on the `points_continue` option in the config file, or set it in the `.env`.

### Events

**UserLevelledUp** - When a User levels-up

```php
public Model $user,
public int $level
```

## 🏆 Achievements

This is a feature that allows you to recognise and reward users for completing specific tasks or reaching certain milestones.
You can define your own achievements and criteria for earning them.
Achievements can be static or have progression.
Static meaning the achievement can be earned instantly.
Achievements with progression can be earned in increments, like an achievement can only be obtained once the progress is 100% complete.

### Creating Achievements

There is no built-in methods for creating achievements, there is just an `Achievement` model that you can use as normal:

```php
Achievement::create([
    'name' => 'Hit Level 20',
    'is_secret' => false,
    'description' => 'When a User hits Level 20',
    'image' => 'storage/app/achievements/level-20.png',
]);
```

### Gain Achievement

To use Achievements in your User model, you must first add the Trait.

```php
// App\Models\User.php

use LevelUp\Experience\Concerns\HasAchievements;

class User extends Authenticable
{
	use HasAchievements;
	
	// ...
}
```

Then you can start using its methods, like to grant a User an Achievement:

```php
$achievement = Achievement::find(1);

$user->grantAchievement($achievement);
```

To retrieve your Achievements:

```php
$user->getUserAchievements();
```

### Revoke Achievement

You can revoke an achievement from a user using the `revokeAchievement` method:

```php
$user->revokeAchievement($achievement);
```

The method will throw an exception if you try to revoke an achievement that the user doesn't have. You can revoke both standard and secret achievements, and it will also remove any associated progress.

When an achievement is revoked, a `AchievementRevoked` event is dispatched.

### Add progress to Achievement

```php
$user->grantAchievement(
    achievement: $achievement, 
    progress: 50 // 50%
);
```

> [!NOTE]
> Achievement progress is capped to 100%

### Check Achievement Progression

Check at what progression your Achievements are at.

```php
$user->achievementsWithProgress()->get();
```

Check Achievements that have a certain amount of progression:

```php
$user->achievementsWithSpecificProgress(25)->get();
```

### Increase Achievement Progression

You can increment the progression of an Achievement up to 100.

```php
$user->incrementAchievementProgress(
    achievement: $achievement, 
    amount: 10
);
```

A `AchievementProgressionIncreased` Event runs on method execution.

### Secret Achievements

Secret achievements are achievements that are hidden from users until they are unlocked.

Secret achievements are made secret when created. If you want to make a non-secret Achievement secret, you can just update the Model.

```php
$achievement->update(['is_secret' => true]);
```

You can retrieve the secret Achievements.

```php
$user->secretAchievements;
```

To view *********all********* Achievements, both secret and non-secret:

```php
$user->allAchievements;
```

### Events

**AchievementAwarded** - When an Achievement is attached to the User

```php
public Achievement $achievement,
public Model $user,
```

> [!NOTE]
> This event only runs if the progress of the Achievement is 100%

**AchievementRevoked** - When an Achievement is detached from the User

```php
public Achievement $achievement,
public Model $user,
```

**AchievementProgressionIncreased** - When a Users’ progression for an Achievement is increased.

```php
public Achievement $achievement,
public Model $user,
public int $amount,
```

## 📈 Leaderboard

The package includes a metric-driven leaderboard. A leaderboard ranks users by a **metric** — experience points by default — and returns `LeaderboardEntry` objects exposing the `user`, their `score`, and their `rank`.

```php
$entries = Leaderboard::generate();

foreach ($entries as $entry) {
    $entry->user;  // the User model (with Experience eager-loaded)
    $entry->score; // the user's score on this metric
    $entry->rank;  // the user's position on the board (1 is first)
}
```

Pass `paginate: true` for a paginator of entries, or `limit:` to cap the result count. Ranks are always board-wide — entry 16 on page 2 still carries rank 16.

### Ranks and ties

Ranks use competition semantics: users with equal scores share a rank, and the next rank is skipped — two users tied for first are both rank 1, and the next user is rank 3. Tied rows are ordered deterministically (score descending, then user key ascending) so pagination boundaries stay stable between requests.

Ranks are computed in the database with SQL window functions, so the leaderboard requires a database that supports them: SQLite 3.25+, MySQL 8+ / MariaDB 10.2+, or PostgreSQL.

### A user's rank

Ask for a single user's exact rank with `rankOf()`, or fetch the slice of the board around them with `around()`:

```php
Leaderboard::rankOf(user: $user); // 4 — or null if the user isn't on the board

Leaderboard::around(user: $user, range: 2); // up to 2 entries above + the user + up to 2 below
```

`rankOf()` returns `null` — and `around()` returns an empty collection — when the user is absent from the board (no experience record, or excluded by the metric's constraints). `around()` clamps at the edges: for the leader it returns the user plus the `range` entries below, and each entry keeps its board-wide rank. Both compose with `by()`:

```php
Leaderboard::by('xp')->rankOf(user: $user);
Leaderboard::by(MyCustomMetric::class)->around(user: $user, range: 3);
```

### Choosing a metric

Metrics are registered in the config under `level-up.leaderboard.metrics`, and the default lives at `level-up.leaderboard.default_metric`. Select one explicitly with `by()` — it accepts a registry key, a class-string, or a metric instance:

```php
Leaderboard::by('xp')->generate();
Leaderboard::by(MyCustomMetric::class)->generate();
Leaderboard::by(new MyCustomMetric())->generate();
```

An unknown key throws `MetricNotFoundException`; a metric whose underlying feature is disabled throws `MetricDisabledException` rather than returning an empty board.

### Built-in metrics

Five metrics ship with the package:

| Key | Ranks by |
| --- | --- |
| `xp` | Experience points (the default) |
| `level` | Current level |
| `streak` | Current streak count for an Activity |
| `achievements` | Number of achievements earned |
| `challenges` | Number of challenges completed |

`level` ranks users by their current level number — users on the same level share a rank:

```php
Leaderboard::by('level')->generate();
```

`streak` ranks users by their current streak count for one Activity, so it needs to know which one. Construct the metric with the Activity and pass the instance:

```php
use LevelUp\Experience\Metrics\StreakMetric;

Leaderboard::by(new StreakMetric(activity: $activity))->generate();
```

Generating a streak board without an Activity — for example via the bare registry key, `by('streak')` — throws `MetricRequiresActivityException`.

Both are **state metrics**: they rank by a current snapshot rather than an accumulation. Users without the relevant record are absent from the board — no level (no experience record) means no entry on the level board; no streak for the given Activity means no entry on that streak board.

`achievements` and `challenges` are **flow metrics**: they rank by an accumulation over time, so they support time periods (see below) as well as all-time boards:

```php
Leaderboard::by('achievements')->generate();                    // most achievements earned, ever
Leaderboard::by('achievements')->period(Period::Week)->generate(); // most achievements earned this week
Leaderboard::by('challenges')->generate();                      // most challenges completed, ever
```

`achievements` counts earned achievements; on a periodic board, only achievements earned within the window count. `challenges` counts **completed** challenges — being enrolled isn't enough — and periodic boards window on when each challenge was completed, not when the user enrolled. If the challenges system is turned off (`level-up.challenges.enabled`), the `challenges` metric throws `MetricDisabledException`. As with every metric, users with a count of zero are absent from the board rather than ranked at zero.

> [!NOTE]
> The `achievements` count includes **secret** achievements. This is deliberate: a count reveals nothing about *which* achievements were earned, and excluding them would let users be punished on the leaderboard for earning a secret. Keep secrecy in what you display, not in the score.

### Time periods

Scope a board to a bounded time window with `period()` — "top earners this week" instead of "highest totals ever":

```php
use LevelUp\Experience\Enums\Period;

Leaderboard::period(Period::Day)->generate();   // points earned today
Leaderboard::period(Period::Week)->generate();  // points earned this week
Leaderboard::period(Period::Month)->generate(); // points earned this month
```

Or pick a custom range with `since()` — an open-ended start, or a bounded `[start, until)` window:

```php
Leaderboard::since(start: now()->subDays(3))->generate();

Leaderboard::since(start: $seasonStart, until: $seasonEnd)->generate();
```

Periodic boards rank by activity *within* the window, sourced from the `experience_audits` ledger: the windowed score is the sum of points **added minus points removed** in the window. State-change audit rows (`reset`, `level_up`, `tier_up`, `tier_down`) never count. Users with no qualifying audit rows in the window are absent from the board. Everything composes as usual — `rankOf()`, `around()`, ties, `limit:`, `paginate:` all work on a periodic board:

```php
Leaderboard::period(Period::Week)->rankOf(user: $user);
Leaderboard::period(Period::Week)->around(user: $user, range: 2);
```

Periodic XP boards require auditing (on by default since v3 — see `level-up.audit.enabled`). If you have explicitly disabled auditing, requesting a periodic XP board throws `MetricRequiresAuditingException` rather than returning a silently empty board. Boards without a period are unaffected: the all-time board keeps reading the cheap `experiences.experience_points` column and never scans the ledger. Periodic `achievements` and `challenges` boards read their own timestamps and work with or without auditing.

Only metrics that implement the `LevelUp\Experience\Contracts\Windowable` interface support periods. The built-in flow metrics — `xp`, `achievements`, and `challenges` — all do: `xp` windows on audit rows, `achievements` on when each achievement was earned, and `challenges` on when each challenge was completed. `level` and `streak` are state metrics — a current level or streak count isn't "earned within a window" — so selecting a period for them throws `MetricNotWindowableException`.

> [!NOTE]
> `setPoints()` is an administrative override, not earned activity — it writes no audit record, so it deliberately never moves a periodic board. The all-time board sees the new total immediately.

Week boundaries and timezones are configurable under `level-up.leaderboard`:

```php
'leaderboard' => [
    // ...
    'week_starts_on' => Carbon\CarbonInterface::MONDAY, // 0 (Sunday) – 6 (Saturday)
    'timezone' => null, // null = the application timezone
],
```

`week_starts_on` sets which day `Period::Week` starts on. `timezone` controls the timezone period boundaries (start of day/week/month) are computed in — useful when your app stores UTC but your users' "today" starts at midnight local time.

### Friends boards and custom populations

The package never owns your social graph — you supply *who*, it supplies the ranking. `restrictTo()` takes a closure that narrows the board's base user query to any population you can express as a query constraint:

```php
$friendIds = $user->friends()->pluck('id')->push($user->id); // include yourself

Leaderboard::restrictTo(fn ($query) => $query->whereIn('id', $friendIds))->generate();
```

Ranks are computed **within** the restricted set, not filtered down from the global board: a user who is rank 40 globally but ahead of all their friends is rank 1 on their friends board. The restriction composes with everything else — `by()`, `period()`/`since()`, `forTier()`, `rankOf()`, and `around()` — in any order:

```php
// This week's XP, friends only
Leaderboard::restrictTo(fn ($query) => $query->whereIn('id', $friendIds))
    ->period(Period::Week)
    ->generate();

// Your rank among your friends — null if you're outside the restriction
Leaderboard::restrictTo(fn ($query) => $query->whereIn('id', $friendIds))->rankOf(user: $user);
```

Friends boards are the headline use, but any host-defined population works the same way — users in a guild, an organisation, a tournament bracket.

### Named Boards

Everything above composes an **ad-hoc query**: you build it fluently, execute it, and it's forgotten. A **Board** is different — a *declared* leaderboard, registered by name in the config as a metric/period(/tier) combination:

```php
'leaderboard' => [
    // ...
    'boards' => [
        'weekly-xp' => ['metric' => 'xp', 'period' => 'week'],
        'gold-race' => ['metric' => 'xp', 'period' => 'week', 'tier' => 'Gold'],
    ],
],
```

Each declaration takes a required `metric` (a registry key from `level-up.leaderboard.metrics`), an optional `period` (`'day'`, `'week'`, or `'month'`), and an optional `tier` (a tier name). Resolve a Board by name with `board()` — it returns the same fluent query, pre-composed, so every refinement still works on top:

```php
Leaderboard::board('weekly-xp')->generate();

Leaderboard::board('weekly-xp')->rankOf(user: $user);

// This week's XP, friends only — refinements compose on top of the declaration
Leaderboard::board('weekly-xp')
    ->restrictTo(fn ($query) => $query->whereIn('id', $friendIds))
    ->generate();
```

Declarations are validated loudly at resolution rather than producing a silently wrong board: an unknown board name throws `BoardNotFoundException`, a missing or unknown `metric` throws `MetricNotFoundException`, a `period` declared for a non-Windowable metric (such as `level`) throws `MetricNotWindowableException`, an invalid `period` value throws a `ValueError`, and a `tier` name with no matching tier throws a `ModelNotFoundException`.

Why declare a board instead of just querying? Boards are the leaderboards the package **tracks over time** — snapshots, rank-change events, and leagues apply only to declared Boards. Ad-hoc queries stay exactly what they are: composed, executed, forgotten. Declare no boards and none of that machinery activates.

### Snapshots and rank events

A **Snapshot** is the leaderboard's memory: a persisted record of a Board's top entries at a point in time. Diffing consecutive snapshots produces rank *deltas* — "you climbed from #5 to #2" — which a stateless query can never compute. The `level-up:snapshot-boards` command writes one snapshot run per declared Board, diffs it against the previous run, dispatches rank events, and prunes old runs.

Schedule the command from your application — the package never auto-registers scheduler entries:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('level-up:snapshot-boards')->hourly();
```

Each run stores the top **tracked depth** entries per Board — `track_top` in the board declaration, default 100:

```php
'boards' => [
    'weekly-xp' => ['metric' => 'xp', 'period' => 'week', 'track_top' => 50],
],
```

Diffing two runs dispatches three events, each carrying the board name:

| Event | Properties | When |
|-------|-----------|------|
| `LeaderboardRankChanged` | `Model $user`, `string $board`, `int $from`, `int $to` | A user moved rank within the tracked depth |
| `UserEnteredTrackedDepth` | `Model $user`, `string $board`, `int $rank` | A user broke into the tracked depth |
| `UserLeftTrackedDepth` | `Model $user`, `string $board`, `int $previousRank` | A user dropped out of the tracked depth |

Below the tracked depth a Board is **silent by design**: no snapshot rows, no events. "You dropped from #6,389 to #6,412" is not a thing the package emits — raise `track_top` if you want deeper tracking and are happy to own the cost. Ties use the same competition semantics as live queries, so a tie breaking only events the users whose rank number actually changed.

Two semantics worth knowing:

- **The first run of a Board is silent.** Events are deltas between runs; with no previous run there is no delta — the first run just writes the baseline snapshot.
- **Re-running within the same instant replaces that run.** A run is identified by its timestamp (to the second), so an immediate re-run overwrites the same run's rows instead of duplicating them, and recomputes the same diff against the run before it.

Old runs are pruned by the same command per `level-up.leaderboard.snapshots.retention_days` (default 30):

```php
'leaderboard' => [
    // ...
    'snapshots' => [
        'retention_days' => 30,
    ],
],
```

> [!NOTE]
> Snapshots are **not a cache** for live queries — `rankOf()` and `around()` always compute fresh, for any user at any depth. Snapshots exist solely to remember past runs so rank deltas can be evented.

### Custom metrics

Rank by anything you can express as a SQL score: implement `LevelUp\Experience\Contracts\RankingMetric` — a stable `key()`, a `label()`, an `enabled()` check, a `constrain()` that scopes the user query to eligible users, and a `scoreExpression()` subquery yielding one numeric score per user — then register the class in `level-up.leaderboard.metrics`.

To support time periods too, also implement `LevelUp\Experience\Contracts\Windowable` — a `windowedScoreExpression($start, $end)` subquery yielding one numeric score per user for activity between the two timestamps (`$end` may be `null` for an open-ended `since()` range).

### Leagues

A **League** is a competitive cycle built on one periodic Board: each period, active users are grouped into small **Cohorts** within a **Division**, and ranked against their cohort-mates only. Declare it in config by binding a Board and a ladder of Divisions, ordered bottom to top:

```php
'leaderboard' => [
    // ...
    'league' => [
        'board' => 'weekly-xp', // must be declared under 'boards' with a 'period'
        'cohort_size' => 30,
        'divisions' => [
            'Bronze' => ['promote' => 10, 'relegate' => 0],
            'Silver' => ['promote' => 7, 'relegate' => 5],
            'Gold' => ['promote' => 0, 'relegate' => 5],
        ],
    ],
],
```

Leave `board` as `null` (the default) and the league machinery stays dormant. The configuration is validated loudly: binding a Board that isn't declared throws `BoardNotFoundException`, binding a Board without a `period` throws `LeagueBoardNotPeriodicException` (a league is a cycle — an all-time board cannot host one), and declaring a league with no divisions throws `LeagueDivisionsNotDeclaredException`. Each division's `promote` and `relegate` counts are consumed by the [period rollover](#rollover-promotion-and-relegation).

#### A Division is not a Tier

The two ladders look similar but answer different questions. A **Tier** is *status*: a pure function of your current XP, recalculated whenever your points change. A **Division** is *competition history*: you hold it because of where you placed in last period's cohort, regardless of what your XP is today. A user holds a Tier and competes in a Division simultaneously and independently — adding leagues changes nothing about `HasTiers`, tier columns, or tier events. It's perfectly normal (Duolingo-style) for an app to show a permanent Gold *tier* badge while the user grinds through the Silver *division* this week.

#### Lazy enrollment

Nobody is pre-assigned. A user joins the current period's league on their **first score-earning action** of the period (the `PointsIncreased` event path) — they're placed into the open cohort of their Division, cohorts fill in arrival order, and a new cohort opens when one reaches `cohort_size`. That means:

- **Ghosts are never cohorted.** A user with no qualifying activity in the period appears in no cohort — their Division simply carries over. Relegation punishes losing, not absence.
- **New users enter the bottom Division** on their first earn; returning users re-enter the Division they held.
- **Cohort sizes vary** — the last cohort of a period may be small. Accepted behavior; no skill matching, no backfilling.

The division ladder rows are seeded automatically from config the first time they're needed.

#### User API

Add the `HasLeagues` trait to your user model:

```php
use LevelUp\Experience\Concerns\HasLeagues;
```

```php
$user->currentDivision();   // ?Division — the rung they hold (null until first-ever earn)
$user->currentCohort();     // ?Cohort — this period's cohort (null if not yet enrolled this period)
$user->cohortStandings();   // Collection<LeaderboardEntry> — ranked entries within the user's cohort only
```

`cohortStandings()` runs the league's Board restricted to the user's cohort-mates, so scores and ranks use the Board's own metric and period — rank 1 means first *in the cohort*, not globally. It returns an empty collection for users not in a cohort (and when no league is configured).

#### Rollover: promotion and relegation

The `level-up:league-rollover` command closes out finished periods: for every Cohort of the closed period it computes the final standings **live** (same metric, same window — not from snapshots) and moves users along the ladder. Schedule it from your application to run just after the period boundary — the package never auto-registers scheduler entries:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// e.g. for a weekly league with Monday-start weeks:
Schedule::command('level-up:league-rollover')->weeklyOn(1, '00:05');
```

Within each cohort, movement follows the Division's configured counts:

| Cohort finish | Movement |
|---------------|----------|
| Top `promote` finishers | Move up one Division |
| Bottom `relegate` finishers | Move down one Division |
| Everyone else | Stay put |

The semantics in detail:

- **Top and bottom are walls.** The top Division never promotes and the bottom never relegates, whatever the config says — there is nowhere to go. Declare `promote: 0` on the top rung and `relegate: 0` on the bottom rung anyway; it documents intent.
- **Small cohorts clamp deterministically.** Promoted = min(configured `promote`, cohort size), so a cohort no larger than its `promote` count promotes everyone. The same user is never both promoted and relegated — promotion wins.
- **Ties split by standings order.** Cohort standings order by score, then by user key, so a tie straddling the promote or relegate boundary is resolved by that order — competition rank numbers (1, 1, 3) don't change how many users cross the line.
- **Ghosts don't move.** A user who was never cohorted in the closed period keeps their Division and gets no event — relegation punishes losing, not absence.
- **Idempotent.** Each rolled cohort is stamped with `rolled_over_at`; re-running the command for an already-rolled period is a no-op, so a scheduler double-fire is harmless.
- **Movement lands next period.** The rollover records each mover's new Division (as `next_division_id` on their cohort membership) but enrolls nobody — lazy enrollment places the user into a cohort of their new Division on their first earn of the new period. `currentDivision()` reflects the move immediately.

Each movement dispatches a single event — mirroring the tier event grammar, with a direction enum instead of separate promoted/relegated classes:

| Event | Properties | When |
|-------|-----------|------|
| `UserDivisionChanged` | `Model $user`, `string $board`, `Division $previousDivision`, `Division $newDivision`, `DivisionDirection $direction` | A rollover moved the user up (`DivisionDirection::Promoted`) or down (`DivisionDirection::Relegated`) the ladder |

Non-movers and ghosts dispatch nothing.

The `divisions`, `cohorts`, and `cohort_user` tables ship as package migrations — re-publish migrations and migrate when upgrading.

### Recipes: what the package doesn't build

The package owns the **ranking logic** — scores, ranks, ties, periods, snapshots, leagues. Display and app-specific queries belong to your application, and some "leaderboard" needs don't need leaderboard machinery at all. A few patterns:

**Users ordered by raw XP.** If you just want users sorted by points — no rank numbers, no tie semantics, no time windows — one `orderByDesc` on the experiences table does it:

```php
use LevelUp\Experience\Models\Experience;

$users = Experience::query()
    ->with('user')
    ->orderByDesc('experience_points')
    ->limit(10)
    ->get()
    ->map(fn (Experience $experience) => $experience->user);
```

**Top-N boards are one-liners.** When you do want ranks and ties, compose metrics and periods instead of writing queries:

```php
Leaderboard::generate(limit: 10);                                 // top 10 by XP, all-time
Leaderboard::by('xp')->period(Period::Week)->generate(limit: 10); // top 10 earners this week
Leaderboard::by('achievements')->period(Period::Month)->generate(limit: 3); // this month's achievements podium
```

**Percentile / "top 10%" display.** Derive it from a rank and a total count — the package supplies the rank; your app defines the population:

```php
$rank = Leaderboard::rankOf(user: $user);
$total = User::query()->whereHas('experience')->count();

$topPercent = $rank === null ? null : (int) ceil($rank / $total * 100); // 4 → "top 4%"
```

**A friends board.** Your app owns the social graph; pass it through [`restrictTo()`](#friends-boards-and-custom-populations) and ranks are computed within the friend set — not filtered down from the global board.

The package ships **no UI** — Blade views, Livewire components, and API resources for displaying any of this are deliberately your job.

## 🔍 Auditing

Auditing keeps track each time a User gains points, levels up and what level to. It is **enabled by default** (since v3) because periodic leaderboards source their scores from the audit ledger — set `AUDIT_POINTS=false` (or `level-up.audit.enabled`) to turn it off if you don't need point history or time-windowed boards.

The `type` and `reason` fields will be populated automatically based on the action taken, but you can overwrite these when adding points to a User

```php
$user->addPoints(
    amount: 50,
    multiplier: 2,
    type: AuditType::Add->value,
    reason: "Some reason here",
);
```

> [!NOTE]
> Auditing happens when the `addPoints` and `deductPoints` methods are called. Auditing must be enabled in the config file.

**View a Users’ Audit Experience**

```php
$user->experienceHistory;
```

## 🔥 Streaks

With the Streaks feature, you can track and motivate user engagement by monitoring consecutive daily activities. Whether it's logging in, completing tasks, or any other daily activity, maintaining streaks encourages users to stay active and engaged.

Streaks are controlled in a Trait, so only use the trait if you want to use this feature. Add the Trait to you `User` model

```php
use LevelUp\Experience\Concerns\HasStreaks;

class User extends Model
{
	use HasStreaks;

	// ...
}
```

### Activities

Use the `Activies` model to add new activities that you want to track. Here’s some examples:

- Logs into a website
- Posts an article

### Record a Streak

```php
$activity = Activity::find(1);

$user->recordStreak($activity);
```

This will increment the streak count for the User on this activity. An `Event is ran on increment.

### Break a Streak

Streaks can be broken, both automatically and manually. This puts the count back to `1` to start again. An Event is ran when a streak is broken.

For example, if your streak has had a successful run of 5 days, but a day is skipped and you run the activity on day 7, the streak will be broken and reset back to `1`. Currently, this happens automatically.

### Reset a Streak

You can reset a streak manually if you desire. If `level-up.archive_streak_history.enabled` is true, the streak history will be recorded.

```php
$activity = Activity::find(1);

$user->resetStreak($activity);
```

### Archive Streak Histories

Streaks are recorded, or “archived” by default. When a streak is broken, a record of the streak is recorded. A Model is supplied to use this data.

```php
use LevelUp\Experience\Models\StreakHistory;

StreakHistory::all();
```

### Get Current Streak Count

See the streak count for an activity for a User

```php
$user->getCurrentStreakCount($activity); // 2
```

### Check User Streak Activity

Check if the User has performed a streak for the day

```php
$user->hasStreakToday($activity);
```

### Events

**StreakIncreased** - If an activity happens on a day after the previous day, the streak is increased.

```php
public int $pointsAdded,
public int $totalPoints,
public string $type,
public ?string $reason,
public Model $user,
```

**StreakBroken** - When a streak is broken and the counter is reset.

```php
public Model $user,
public Activity $activity,
public Streak $streak,
```

## 🥶 Streak Freezing

Streaks can be frozen, which means they will not be broken if a day is skipped. This is useful for when you want to allow users to take a break from an activity without losing their streak.

The freeze duration is a configurable option in the config file.

```php
'freeze_duration' => env(key: 'STREAK_FREEZE_DURATION', default: 1),
```

### Freeze a Streak

Fetch the activity you want to freeze and pass it to the `freezeStreak` method. A second parameter can be passed to set the duration of the freeze. The default is `1` day (as set in the config)

A `StreakFrozen` Event is ran when a streak is frozen.

```php
$user->freezeStreak(activity: $activity);

$user->freezeStreak(activity: $activity, days: 5); // freeze for 5 days
```

### Unfreeze a Streak

The opposite of freezing a streak is unfreezing it. This will allow the streak to be broken again.

A `StreakUnfrozen` Event is run when a streak is unfrozen.

```php
$user->unfreezeStreak($activity);
```

### Check if a Streak is Frozen

```php
$user->isStreakFrozen($activity);
```

### Events

**StreakFrozen** - When a streak is frozen.

```php
public int $frozenStreakLength,
public Carbon $frozenUntil,
```

**StreakUnfrozen** - When a streak is unfrozen.

```
No data is sent with this event
```

## 🏅 Tiers

Tiers provide named status brackets based on experience points — think Bronze, Silver, Gold, Platinum. Unlike levels (which are numeric progression), tiers represent **status** and can integrate with multipliers, achievements, streaks, and leaderboards.

Add the `HasTiers` trait to your `User` model:

```php
use LevelUp\Experience\Concerns\HasTiers;

class User extends Model
{
    use GiveExperience, HasAchievements, HasStreaks, HasTiers;
}
```

### Define Tiers

```php
use LevelUp\Experience\Models\Tier;

Tier::add(
    ['name' => 'Bronze', 'experience' => 0],
    ['name' => 'Silver', 'experience' => 500],
    ['name' => 'Gold', 'experience' => 2000],
    ['name' => 'Platinum', 'experience' => 5000, 'metadata' => ['color' => '#E5E4E2']],
);
```

The `metadata` column is a flexible JSON field — store whatever you need (colours, icons, descriptions).

### Query Tiers

```php
$user->getTier();           // Current tier (Tier model or null)
$user->getNextTier();       // Next tier above current
$user->tierProgress();      // Percentage (0-100) through current bracket
$user->nextTierAt();        // XP remaining until next tier
$user->isAtTier('Gold');    // Check exact tier
$user->isAtOrAboveTier('Silver'); // Check tier hierarchy
```

### Demotion

By default, tiers use a **high-water mark** — once a user reaches Gold, they stay Gold even if points decrease. To enable demotion (tier drops when points drop):

```
TIER_DEMOTION=true
```

### Tier-Scoped Multipliers

You can create multipliers that only apply to Users in specific tiers using `scopeToTier()` (see the [Multipliers](#multipliers) section above):

```php
$goldMultiplier = Multiplier::create([
    'name' => 'Gold Tier Bonus',
    'multiplier' => 2,
    'is_active' => true,
]);

$goldMultiplier->tiers()->attach(Tier::where('name', 'Gold')->first());
```

### Tier-Gated Achievements

Restrict achievements to users who have reached a certain tier:

```php
$goldTier = Tier::where('name', 'Gold')->first();

Achievement::create([
    'name' => 'Golden Streak',
    'tier_id' => $goldTier->id,
]);
```

Attempting to grant this achievement to a user below Gold will throw `TierRequirementNotMet`.

### Tier-Scaled Streak Freezes

Higher tiers can get longer freeze durations:

```php
// config/level-up.php
'tiers' => [
    'streak_freeze_days' => [
        'Bronze' => 1,
        'Silver' => 2,
        'Gold' => 3,
        'Platinum' => 7,
    ],
],
```

### Tier-Scoped Leaderboards

Filter leaderboards by tier:

```php
Leaderboard::forTier('Gold')->generate();
```

### Events

**UserTierUpdated** — When a user's tier changes (promotion or demotion).

```php
public Model $user,
public ?Tier $previousTier,
public ?Tier $newTier,
public TierDirection $direction, // TierDirection::Promoted or TierDirection::Demoted
```

## 🎯 Challenges

Challenges are multi-condition goals that users can enroll in and complete for rewards. Think "Earn 100 XP and reach Level 5 to unlock a bonus." Challenges support auto-enrollment, time windows, repeatable completion, and custom condition logic.

Add the `HasChallenges` trait to your `User` model:

```php
use LevelUp\Experience\Concerns\HasChallenges;

class User extends Model
{
    use GiveExperience, HasAchievements, HasStreaks, HasTiers, HasChallenges;
}
```

### Creating Challenges

```php
use LevelUp\Experience\Models\Challenge;

Challenge::create([
    'name' => 'Welcome Warrior',
    'description' => 'Complete these tasks to earn a bonus!',
    'conditions' => [
        ['type' => 'points_earned', 'amount' => 100],
        ['type' => 'level_reached', 'level' => 5],
    ],
    'rewards' => [
        ['type' => 'points', 'amount' => 500],
    ],
    'auto_enroll' => true,
    'is_repeatable' => false,
    'starts_at' => '2026-04-01 00:00:00', // optional
    'expires_at' => '2026-04-30 23:59:59', // optional
]);
```

> [!NOTE]
> Conditions and rewards are validated on creation. Invalid types or missing required keys will throw an `InvalidArgumentException`.

### Condition Types

| Type | Required Keys | What it checks |
|------|--------------|----------------|
| `points_earned` | `amount` | Points earned since enrollment |
| `level_reached` | `level` | User's current level >= target |
| `achievement_earned` | `achievement_id` | User has the achievement |
| `streak_count` | `activity`, `count` | Current streak count for the activity |
| `tier_reached` | `tier` | User is at or above the named tier |
| `leaderboard_rank` | `board`, `rank` | User's rank on the named Board is at or above the target |
| `custom` | `class` | Your own class implementing `ChallengeCondition` |

### Leaderboard Rank Conditions

The `leaderboard_rank` condition is "finish top N on a named Board" — it is met when the user's rank on the Board, as recorded by the latest snapshot run, is at or above the target (`rank <= N`):

```php
Challenge::create([
    'name' => 'Podium Finish',
    'description' => 'Crack the top 3 on the weekly XP board.',
    'conditions' => [
        ['type' => 'leaderboard_rank', 'board' => 'weekly-xp', 'rank' => 3],
    ],
    'rewards' => [
        ['type' => 'points', 'amount' => 500],
    ],
    'auto_enroll' => true,
]);
```

> [!IMPORTANT]
> This condition only progresses when `level-up:snapshot-boards` runs — schedule it (see [Snapshots and rank events](#snapshots-and-rank-events)). Progress is evaluated on the rank events the snapshot run dispatches, and the rank is read from the run's snapshot rows, so a board that is never snapshotted never satisfies the condition.

Validation on challenge creation is strict, so a misconfigured condition fails loudly instead of silently never completing:

- `board` must be a Board declared in `level-up.leaderboard.boards`.
- `rank` must be a positive integer within the Board's tracked depth (`track_top`, default 100). A condition targeting rank 150 on a board tracking the top 100 is rejected — below the tracked depth the board is silent, so the condition could never be met. Raise the Board's `track_top` if you need deeper targets.

### Reward Types

| Type | Required Keys | What happens |
|------|--------------|--------------|
| `points` | `amount` | Adds XP to the user |
| `achievement` | `achievement_id` | Grants an achievement |

### Enrolling Users

**Auto-enroll** — Set `auto_enroll` to `true` on the challenge. Users are enrolled automatically when a relevant event fires (e.g. earning points, levelling up). Enrollment starts the clock: "earn 100 points" means 100 more points from the moment of enrollment, not total lifetime points.

**Manual enroll:**

```php
$challenge = Challenge::find(1);

$user->enrollInChallenge($challenge);
```

Manual enrollment throws if the challenge hasn't started yet, has expired, or the user is already enrolled.

**Unenroll:**

```php
$user->unenrollFromChallenge($challenge);
```

Throws if the user is not enrolled, or if the challenge is already completed.

### Querying Progress

```php
$user->activeChallenges;        // Enrolled, not yet completed
$user->completedChallenges;     // Completed challenges

$user->getChallengeProgress($challenge);
// Returns: [['type' => 'points_earned', 'completed' => true], ['type' => 'level_reached', 'completed' => false]]

$user->getChallengeCompletionPercentage($challenge);
// Returns: 50.0 (1 of 2 conditions met)
```

### Custom Conditions

Implement the `ChallengeCondition` contract for your own logic:

```php
use LevelUp\Experience\Contracts\ChallengeCondition;
use Illuminate\Database\Eloquent\Model;

class HasVerifiedEmail implements ChallengeCondition
{
    public function check(Model $user, array $condition): bool
    {
        return $user->hasVerifiedEmail();
    }
}
```

Then reference it in your challenge conditions:

```php
Challenge::create([
    'conditions' => [
        ['type' => 'custom', 'class' => HasVerifiedEmail::class],
    ],
    // ...
]);
```

### Repeatable Challenges

Set `is_repeatable` to `true`. When all conditions are met, rewards are dispatched, then the challenge resets with a fresh baseline. The user can complete it again.

### Events

**ChallengeCompleted** — When all conditions are met and rewards are dispatched.

```php
public Challenge $challenge,
public Model $user,
```

**ChallengeEnrolled** — When a user enrolls in a challenge (manual or auto).

```php
public Challenge $challenge,
public Model $user,
```

**ChallengeUnenrolled** — When a user unenrolls from a challenge.

```php
public Challenge $challenge,
public Model $user,
```

### Configuration

Challenges are enabled by default. To disable:

```
CHALLENGES_ENABLED=false
```

# Customizing Identifiers

By default, the package's tables use auto-incrementing `bigint` primary keys. Set `level-up.entities.id_type` to `uuid` or `ulid` if you want package IDs to be opaque — useful when exposing Experience or Achievement records on a public API without leaking row counts.

```php
'entities' => [
    'id_type' => 'uuid', // 'bigint' (default) | 'uuid' | 'ulid'
],
```

This applies to every package primary key (experiences, levels, achievements, streaks, tiers, multipliers, challenges, and the pivot tables) and every internal foreign key between them. One set of columns is intentionally unaffected:

- `user_id` columns — these match whatever your `users` table uses, since they belong to the host application. The dedicated `level-up.user.foreign_key_type` config knob controls those independently of `entities.id_type`.

> [!IMPORTANT]
> This setting is for **fresh installs**. Existing installs cannot be flipped automatically — column types are baked in at migration time. The accordion below contains an AI prompt that generates the conversion migrations for your specific schema.

<details>
<summary>AI prompt: convert an existing install to <code>uuid</code> or <code>ulid</code></summary>

Paste this into your AI assistant. Replace `<TARGET>` with `uuid` or `ulid` and `<DB>` with `postgres`, `mysql`, or `sqlite`.

```text
I'm switching cjmellor/level-up's `entities.id_type` from `bigint` to `<TARGET>` on an existing `<DB>` install.

Generate a Laravel migration (or sequence of migrations) that:

1. For every level-up package table (experiences, levels, achievements, achievement_user, streak_activities, streaks, streak_histories, tiers, multipliers, multiplier_user, multiplier_tier, challenges, challenge_user, experience_audits): add a new `<TARGET>` column called `id_new`, generate a unique value for every existing row, then later drop the old `id` and rename `id_new` to `id`.

2. For every internal foreign key column (`level_id`, `activity_id`, `achievement_id`, `challenge_id`, `tier_id`, `multiplier_id`, etc.): add a corresponding `_new` column, populate it by joining on the parent table's new ids, then drop the old column and rename.

3. Re-establish primary key and foreign key constraints in the correct order. Wrap in a transaction if `<DB>` supports DDL transactions.

Constraints:
- Do NOT change `user_id` columns in any package table — those follow the host's `users` table type and are controlled by `level-up.user.foreign_key_type` separately.
- After the migration runs, update `level-up.entities.id_type` in `config/level-up.php` to `<TARGET>`.
```

Review the generated migration carefully against your schema and data volume before running it on production.

</details>

# Testing

```
composer test
```

# Changelog

Please see [CHANGELOG](notion://www.notion.so/CHANGELOG.md) for more information on what has changed recently.

# License

The MIT Licence (MIT). Please see [Licence File](notion://www.notion.so/LICENSE.md) for more information.
