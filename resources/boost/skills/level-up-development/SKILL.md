---
name: level-up-development
description: Build and work with cjmellor/level-up features, including XP, levels, tiers, achievements, streaks, multipliers, leaderboards, and auditing.
---

## When to use this skill

Use this skill when working with gamification features — adding experience points, levels, tiers, achievements, streaks, multipliers, leaderboards, or auditing — using cjmellor/level-up.

## Core Concepts

- **XP and Levels** — Users earn experience points (XP) and automatically progress through a defined level structure.
- **Tiers** — Named status brackets (e.g. Bronze, Silver, Gold) based on XP thresholds, independent of numeric levels.
- **Achievements** — Unlockable rewards, optionally with progress tracking, optionally gated by tier.
- **Streaks** — Track consecutive daily activities with freeze support.
- **Multipliers** — Class-based, conditional, or manual point modifiers.
- **Leaderboard** — Rank users by XP, optionally scoped to a tier.
- **Auditing** — Automatic history of all XP changes, level-ups, and tier changes.

## Setup

### Install

```bash
composer require cjmellor/level-up
php artisan vendor:publish --tag="level-up-migrations"
php artisan migrate
php artisan vendor:publish --tag="level-up-config"
```

### Add Traits to the User Model

Add only the traits you need:

```php
use LevelUp\Experience\Concerns\GiveExperience;
use LevelUp\Experience\Concerns\HasAchievements;
use LevelUp\Experience\Concerns\HasStreaks;
use LevelUp\Experience\Concerns\HasTiers;

class User extends Authenticatable
{
    use GiveExperience;     // Required — XP and levels
    use HasAchievements;    // Optional — achievements
    use HasStreaks;          // Optional — streaks
    use HasTiers;           // Optional — tiers
}
```

`GiveExperience` is the foundation. The others are opt-in.

## Levels

### Define Levels

```php
use LevelUp\Experience\Models\Level;

Level::add(
    ['level' => 1, 'next_level_experience' => null],
    ['level' => 2, 'next_level_experience' => 100],
    ['level' => 3, 'next_level_experience' => 250],
    ['level' => 4, 'next_level_experience' => 500],
    ['level' => 5, 'next_level_experience' => 1000],
);
```

Level 1 must have `next_level_experience` set to `null` — it is the default starting point. Users level up automatically when their XP reaches the threshold. Throws `LevelExistsException` if a level number already exists.

### Level Queries

```php
$user->getLevel();       // Current level number (int), 0 if no experience
$user->getPoints();      // Current XP total (int), 0 if no experience
$user->nextLevelAt();    // XP remaining until next level (int)
$user->nextLevelAt(checkAgainst: 5); // XP remaining until level 5
$user->nextLevelAt(showAsPercentage: true); // Progress as 0-100 percentage
```

### Manual Level Up

```php
$user->levelUp(to: 5); // Jump to level 5
```

Throws `InvalidArgumentException` if the level does not exist. Fires `UserLevelledUp` for each intermediate level gained. Respects the level cap.

### Level Cap

Configured in `config/level-up.php`:

```php
'level_cap' => [
    'enabled' => env('LEVEL_CAP_ENABLED', true),
    'level' => env('LEVEL_CAP', 100),
    'points_continue' => env('LEVEL_CAP_POINTS_CONTINUE', true),
],
```

When the cap is reached, the user stops levelling. If `points_continue` is `true`, XP still accumulates. If `false`, XP stops accumulating too.

## Experience Points (XP)

### Add Points

```php
$user->addPoints(50);
$user->addPoints(50, reason: 'Completed tutorial');
$user->addPoints(50, multiplier: 2);
$user->addPoints(50, type: AuditType::Add->value, reason: 'Bonus');
```

Creates an experience record if none exists, otherwise increments. Automatically levels up if the threshold is crossed. Throws if the amount exceeds the highest level's `next_level_experience`.

### Deduct Points

```php
$user->deductPoints(30);
$user->deductPoints(30, reason: 'Penalty');
```

Throws `Exception` if the user has no experience record.

### Set Points

```php
$user->setPoints(500);
```

Directly overwrites the XP total. Throws `Exception` if the user has no experience record.

### Get Points

```php
$user->getPoints(); // int, returns 0 if no experience record
```

## Multipliers

### Class-Based Multipliers

Generate a multiplier class:

```bash
php artisan level-up:multiplier IsWeekend
```

Creates `app/Multipliers/IsWeekend.php`:

```php
use LevelUp\Experience\Contracts\Multiplier;

class IsWeekend implements Multiplier
{
    public bool $enabled = true;

    public function qualifies(array $data): bool
    {
        return now()->isWeekend();
    }

    public function setMultiplier(): int
    {
        return 2;
    }
}
```

All enabled multiplier classes in the configured path are auto-discovered and applied when `addPoints()` is called. Set `$enabled = false` to disable without deleting.

### Multipliers with Data

Pass contextual data that multiplier classes can inspect:

```php
$user
    ->withMultiplierData(['event_id' => 42])
    ->addPoints(10);

// In the multiplier class:
public function qualifies(array $data): bool
{
    return isset($data['event_id']) && $data['event_id'] === 42;
}
```

### Conditional Multipliers (Inline)

```php
$user
    ->withMultiplierData(fn () => $someCondition)
    ->addPoints(amount: 10, multiplier: 2);
```

The callback must return `true` for the multiplier to apply. The `multiplier` parameter is required when using a callback — throws `InvalidArgumentException` if omitted.

### Manual Multiplier

```php
$user->addPoints(amount: 10, multiplier: 3); // 30 points
```

### Multiplier Config

```php
'multiplier' => [
    'enabled' => env('MULTIPLIER_ENABLED', true),
    'path' => env('MULTIPLIER_PATH', app_path('Multipliers')),
    'namespace' => env('MULTIPLIER_NAMESPACE', 'App\\Multipliers\\'),
],
```

## Tiers

### Define Tiers

```php
use LevelUp\Experience\Models\Tier;

Tier::add(
    ['name' => 'Bronze', 'experience' => 0],
    ['name' => 'Silver', 'experience' => 500],
    ['name' => 'Gold', 'experience' => 2000],
    ['name' => 'Platinum', 'experience' => 5000, 'metadata' => ['color' => '#E5E4E2', 'icon' => 'crown']],
);
```

Tier names and experience values must be unique. Throws `TierExistsException` on duplicates. The `metadata` column is a flexible JSON field for any extra data (colours, icons, descriptions). The entire `add()` call is wrapped in a database transaction — if any tier fails, none are created.

### Automatic Tier Promotion

Tiers update automatically when XP changes. When `addPoints()` causes the user to cross a tier threshold, `experience.tier_id` is updated and a `UserTierUpdated` event fires with `TierDirection::Promoted`.

### Query Tiers

```php
$user->getTier();                    // Current Tier model or null
$user->getNextTier();                // Next tier above current (Tier or null)
$user->tierProgress();               // Percentage 0-100 through current bracket
$user->nextTierAt();                 // XP remaining until next tier
$user->isAtTier('Gold');             // Exact match (bool)
$user->isAtOrAboveTier('Silver');    // At or above (bool)
```

`getTier()` returns `null` if the user has no experience record or tiers are disabled.

### Demotion

By default, tiers use a **high-water mark** — once earned, they persist even if points decrease. Enable demotion to allow tier drops:

```
TIER_DEMOTION=true
```

When enabled, `deductPoints()` checks if the user should drop and fires `UserTierUpdated` with `TierDirection::Demoted`. The `newTier` property is nullable — it will be `null` if the user drops below all tier thresholds.

### Tier Multipliers

Automatically scale all points earned based on the user's current tier:

```php
// config/level-up.php
'tiers' => [
    'multipliers' => [
        'Bronze' => 1,
        'Silver' => 1.5,
        'Gold' => 2,
    ],
],
```

Applied after class-based and manual multipliers.

### Tier-Gated Achievements

Restrict achievements so only users at a certain tier can earn them:

```php
$goldTier = Tier::where('name', 'Gold')->first();

Achievement::create([
    'name' => 'Golden Streak',
    'tier_id' => $goldTier->id,
]);
```

Attempting to grant to a user below Gold throws `TierRequirementNotMet`.

### Tier-Scaled Streak Freezes

Higher tiers get longer freeze durations:

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

Falls back to the global `freeze_duration` if the tier is not listed or tiers are disabled.

### Tier-Scoped Leaderboards

```php
use LevelUp\Experience\Facades\Leaderboard;

Leaderboard::forTier('Gold')->generate();
Leaderboard::forTier($tierModel)->generate();
```

### Tier Config

```php
'tiers' => [
    'enabled' => env('TIERS_ENABLED', true),
    'demotion' => env('TIER_DEMOTION', false),
    'multipliers' => [],
    'streak_freeze_days' => [],
],
```

## Achievements

### Create Achievements

```php
use LevelUp\Experience\Models\Achievement;

Achievement::create([
    'name' => 'First Login',
    'is_secret' => false,
    'description' => 'Log in for the first time',
    'image' => 'storage/app/achievements/first-login.png',
]);

// Secret achievement (hidden until earned)
Achievement::create([
    'name' => 'Hidden Gem',
    'is_secret' => true,
]);

// Tier-gated achievement
Achievement::create([
    'name' => 'Gold Member Badge',
    'tier_id' => $goldTier->id,
]);
```

### Grant Achievement

```php
$user->grantAchievement($achievement);

// With progress (0-100)
$user->grantAchievement($achievement, progress: 50);
```

Throws `Exception` if progress exceeds 100, or if the user already has the achievement. Throws `TierRequirementNotMet` if tier-gated and user does not meet the tier requirement. `AchievementAwarded` event fires only when progress is `null` or `100`.

### Revoke Achievement

```php
$user->revokeAchievement($achievement);
```

Throws `Exception` if the user does not have the achievement.

### Achievement Progress

```php
$newProgress = $user->incrementAchievementProgress($achievement, amount: 10);

$user->achievementsWithProgress()->get();
$user->achievementsWithSpecificProgress(75)->get();
```

`incrementAchievementProgress()` throws `Exception` if the user does not have the achievement. Grant it first. Progress is capped at 100.

### Query Achievements

```php
$user->achievements;               // Non-secret achievements
$user->secretAchievements;          // Secret achievements only
$user->allAchievements;            // Both
$user->getUserAchievements();      // Same as $user->achievements
```

## Streaks

### Create Activities

```php
use LevelUp\Experience\Models\Activity;

Activity::create(['name' => 'daily-login', 'description' => 'User logs in']);
```

### Record a Streak

```php
$activity = Activity::where('name', 'daily-login')->first();

$user->recordStreak($activity);
```

- First call: creates streak (count = 1), fires `StreakStarted`
- Same day: no-op
- Next consecutive day: increments count, fires `StreakIncreased`
- Skipped a day: resets to 1, fires `StreakBroken` (archives if enabled)
- Streak frozen: no-op until freeze expires

### Query Streaks

```php
$user->getCurrentStreakCount($activity); // int (0 if no streak)
$user->hasStreakToday($activity);        // bool
$user->streaks;                          // HasMany relationship
```

### Reset / Freeze / Unfreeze

```php
$user->resetStreak($activity);
$user->freezeStreak($activity);               // Uses config or tier-scaled duration
$user->freezeStreak($activity, days: 5);       // Custom duration
$user->unFreezeStreak($activity);
$user->isStreakFrozen($activity);              // bool
```

### Streak History

When a streak breaks, it is archived automatically (if enabled):

```php
use LevelUp\Experience\Models\StreakHistory;

$histories = StreakHistory::where('user_id', $user->id)->get();
```

### Streak Config

```php
'archive_streak_history' => [
    'enabled' => env('ARCHIVE_STREAK_HISTORY_ENABLED', true),
],
'freeze_duration' => env('STREAK_FREEZE_DURATION', 1),
```

## Leaderboard

```php
use LevelUp\Experience\Facades\Leaderboard;

Leaderboard::generate();                        // All users by XP
Leaderboard::generate(paginate: true);          // Paginated
Leaderboard::generate(limit: 10);              // Top 10
Leaderboard::forTier('Gold')->generate();       // Gold tier only
```

Returns User models with `experience` relationship eager-loaded, ordered by XP descending.

## Auditing

Enable in config:

```php
'audit' => [
    'enabled' => env('AUDIT_POINTS', false),
],
```

When enabled, every `addPoints()`, `deductPoints()`, `levelUp()`, and tier change creates an `experience_audits` record.

```php
$user->experienceHistory;   // HasMany to ExperienceAudit
```

Audit types use the `AuditType` enum:

```php
use LevelUp\Experience\Enums\AuditType;

AuditType::Add;      // 'add'
AuditType::Remove;   // 'remove'
AuditType::Reset;    // 'reset'
AuditType::LevelUp;  // 'level_up'
AuditType::TierUp;   // 'tier_up'
AuditType::TierDown; // 'tier_down'
```

## Events

| Event | Properties | When |
|-------|-----------|------|
| `PointsIncreased` | `int $pointsAdded`, `int $totalPoints`, `string $type`, `?string $reason`, `Model $user` | Points added |
| `PointsDecreased` | `int $pointsDecreasedBy`, `int $totalPoints`, `?string $reason`, `Model $user` | Points deducted |
| `UserLevelledUp` | `Model $user`, `int $level` | Level gained (fires per level) |
| `UserTierUpdated` | `Model $user`, `?Tier $previousTier`, `?Tier $newTier`, `TierDirection $direction` | Tier promotion or demotion |
| `AchievementAwarded` | `Achievement $achievement`, `Model $user` | Achievement granted at 100% |
| `AchievementRevoked` | `Achievement $achievement`, `Model $user` | Achievement revoked |
| `AchievementProgressionIncreased` | `Achievement $achievement`, `Model $user`, `int $amount` | Progress incremented |
| `StreakStarted` | `Model $user`, `Activity $activity`, `Streak $streak` | First streak record |
| `StreakIncreased` | `Model $user`, `Activity $activity`, `Streak $streak` | Consecutive day |
| `StreakBroken` | `Model $user`, `Activity $activity`, `Streak $streak` | Streak reset |
| `StreakFrozen` | `int $frozenStreakLength`, `Carbon $frozenUntil` | Streak frozen |
| `StreakUnfroze` | *(none)* | Streak unfrozen |

## Common Patterns

### User Profile with Level and Tier

```php
$user = User::with(['experience.status', 'experience.tier'])->find($id);

$data = [
    'level' => $user->getLevel(),
    'points' => $user->getPoints(),
    'next_level_in' => $user->nextLevelAt(),
    'level_progress' => $user->nextLevelAt(showAsPercentage: true),
    'tier' => $user->getTier()?->name,
    'tier_progress' => $user->tierProgress(),
    'next_tier_in' => $user->nextTierAt(),
];
```

### Level-Up Reward via Event

```php
use LevelUp\Experience\Events\UserLevelledUp;

Event::listen(UserLevelledUp::class, function (UserLevelledUp $event) {
    if ($event->level === 10) {
        $achievement = Achievement::where('name', 'Hit Level 10')->first();
        if ($achievement) {
            $event->user->grantAchievement($achievement);
        }
    }
});
```

### Seeding Levels and Tiers

```php
class GamificationSeeder extends Seeder
{
    public function run(): void
    {
        Level::add(
            ['level' => 1, 'next_level_experience' => null],
            ['level' => 2, 'next_level_experience' => 100],
            ['level' => 3, 'next_level_experience' => 250],
        );

        Tier::add(
            ['name' => 'Bronze', 'experience' => 0],
            ['name' => 'Silver', 'experience' => 500],
            ['name' => 'Gold', 'experience' => 2000],
        );

        Activity::create(['name' => 'daily-login']);
    }
}
```

## Config Reference

All model classes in the `models` config array can be overridden to use custom models. The `user.foreign_key` defaults to `user_id` and can be customised for non-standard setups.

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
    ],
    'user' => [
        'foreign_key' => 'user_id',
        'model' => App\Models\User::class,
        'users_table' => 'users',
    ],
    'table' => 'experiences',
    'starting_level' => 1,
    'multiplier' => [
        'enabled' => env('MULTIPLIER_ENABLED', true),
        'path' => env('MULTIPLIER_PATH', app_path('Multipliers')),
        'namespace' => env('MULTIPLIER_NAMESPACE', 'App\\Multipliers\\'),
    ],
    'level_cap' => [
        'enabled' => env('LEVEL_CAP_ENABLED', true),
        'level' => env('LEVEL_CAP', 100),
        'points_continue' => env('LEVEL_CAP_POINTS_CONTINUE', true),
    ],
    'audit' => [
        'enabled' => env('AUDIT_POINTS', false),
    ],
    'archive_streak_history' => [
        'enabled' => env('ARCHIVE_STREAK_HISTORY_ENABLED', true),
    ],
    'freeze_duration' => env('STREAK_FREEZE_DURATION', 1),
    'tiers' => [
        'enabled' => env('TIERS_ENABLED', true),
        'demotion' => env('TIER_DEMOTION', false),
        'multipliers' => [],
        'streak_freeze_days' => [],
    ],
];
```
