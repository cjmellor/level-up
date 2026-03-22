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

    'tiers' => [
        'enabled' => env(key: 'TIERS_ENABLED', default: true),
        'demotion' => env(key: 'TIER_DEMOTION', default: false),
        'streak_freeze_days' => [],
    ],
];
```

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
// Scope to specific tiers
$multiplier->tiers()->attach([$premiumTier->id, $diamondTier->id]);

// Scope to a specific user
$multiplier->users()->attach($user->id);

// Or use the convenience method with any model
$multiplier->scopeTo($premiumTier, $diamondTier, $user);
```

If a multiplier has no scopes, it applies to everyone. If it has scopes, it only applies to Users who match (either directly or via their Tier).

You can also use `detach()` and `sync()` on the `tiers()` and `users()` relationships.

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

The package also includes a leaderboard feature to track and display user rankings based on their experience points.

The Leaderboard comes as a Service.

```php
Leaderboard::generate();
```

This generates a User model along with its Experience and Level data and ordered by the Users’ experience points.

> The Leaderboard is very basic and has room for improvement
>

## 🔍 Auditing

You can enable an Auditing feature in the config, which keeps a track each time a User gains points, levels up and what level to.

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

You can create multipliers that only apply to Users in specific tiers using the polymorphic scoping system (see the [Multipliers](#multipliers) section above):

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

# Testing

```
composer test
```

# Changelog

Please see [CHANGELOG](notion://www.notion.so/CHANGELOG.md) for more information on what has changed recently.

# License

The MIT Licence (MIT). Please see [Licence File](notion://www.notion.so/LICENSE.md) for more information.
