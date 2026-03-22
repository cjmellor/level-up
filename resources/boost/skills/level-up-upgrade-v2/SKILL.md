---
name: level-up-upgrade-v2
description: Automatically upgrade cjmellor/level-up from v1 to v2, applying all breaking changes to the user's codebase.
---

## When to use this skill

Use this skill when a user needs to upgrade from cjmellor/level-up v1.x to v2.x.

## Step 1: Pre-flight checks

1. Confirm the user has backed up their database.
2. Run `php artisan migrate:status` to ensure no pending migrations.
3. Run `php artisan test` to ensure the test suite passes before starting.

If tests fail, stop and resolve with the user before proceeding.

## Step 2: Update the package

Run:

```bash
composer require cjmellor/level-up:"^2.0"
```

This requires **PHP 8.3+** and **Laravel 12 or 13**. If Composer fails on PHP or Laravel constraints, inform the user they must upgrade PHP/Laravel first.

## Step 3: Publish and run new migrations

Run these commands in order:

```bash
php artisan vendor:publish --tag="level-up-migrations"
php artisan migrate
```

This publishes and runs these new migrations:

- `alter_experience_audits_type_to_string` — converts the `type` column from `enum` to `string` (required for new `tier_up`/`tier_down` audit types)
- `create_tiers_table` — creates the `tiers` table
- `add_tier_id_to_experiences_table` — adds `tier_id` foreign key to the experiences table
- `add_tier_id_to_achievements_table` — adds `tier_id` foreign key to the achievements table

Existing migrations (from v1) will be skipped if they have already run.

## Step 4: Re-publish the config

The config now includes a `tiers` section and a `tier` model entry. Run:

```bash
php artisan vendor:publish --tag="level-up-config" --force
```

Then review the diff — the user may have customised values in the old config that need to be carried forward.

New config keys added in v2:

| Key | Default | Purpose |
|-----|---------|---------|
| `models.tier` | `LevelUp\Experience\Models\Tier::class` | Tier model class |
| `tiers.enabled` | `true` | Enable/disable the tier system |
| `tiers.demotion` | `false` | Allow tier demotion when points decrease |
| `tiers.multipliers` | `[]` | Map tier names to point multiplier values |
| `tiers.streak_freeze_days` | `[]` | Map tier names to streak freeze durations |

## Step 5: Apply code changes

For each change below, use Grep to search the user's `app/`, `config/`, `routes/`, `database/`, and `tests/` directories. Edit every file that matches.

### 5a. `Level::add()` scalar form removed

**Search:** `Level::add(` — then inspect each call site.

**Action:** The scalar form `Level::add(level: 1, pointsToNextLevel: 100)` has been removed. Convert all calls to the array form:

```php
// Before (v1)
Level::add(level: 1, pointsToNextLevel: 100);

// After (v2)
Level::add(['level' => 1, 'next_level_experience' => 100]);
```

Note: the parameter name also changed from `pointsToNextLevel` to `next_level_experience`. If already using the array form with the correct key, no change is needed.

### 5b. `levelUp()` now throws on invalid levels

**Search:** `->levelUp(`

**Action:** In v1, calling `$user->levelUp(to: 999)` with a non-existent level silently did nothing. In v2, it throws `InvalidArgumentException`. If any call site passes a dynamic value, wrap it in a try/catch or validate first:

```php
$levelClass = config('level-up.models.level');
if ($levelClass::where('level', $targetLevel)->exists()) {
    $user->levelUp(to: $targetLevel);
}
```

### 5c. `deductPoints()` now throws when no experience record exists

**Search:** `->deductPoints(`

**Action:** In v1, calling this on a user with no experience record silently returned. In v2, it throws `Exception`. Guard if necessary:

```php
if ($user->experience()->exists()) {
    $user->deductPoints(50);
}
```

### 5d. `incrementAchievementProgress()` now throws on missing achievement

**Search:** `->incrementAchievementProgress(`

**Action:** In v1, calling this on an achievement the user didn't have caused a null dereference. In v2, it throws a clear `Exception`: "User does not have this Achievement. Grant it first before incrementing progress."

```php
if ($user->achievements()->find($achievement->id)) {
    $user->incrementAchievementProgress($achievement, amount: 10);
}
```

### 5e. `grantAchievement()` progress parameter is now typed

**Search:** `->grantAchievement(`

**Action:** The `$progress` parameter is now typed as `?int`. Cast any non-integer values:

```php
// Before (v1)
$user->grantAchievement($achievement, progress: '50');

// After (v2)
$user->grantAchievement($achievement, progress: 50);
```

### 5f. `getStreakLastActivity()` return type changed

**Search:** `->getStreakLastActivity(`

**Action:** The method now returns `?Streak` instead of `Streak`. This is a `protected` method — it only matters if the user has overridden it or calls it from a subclass. Ensure any code handles the `null` case.

### 5g. `scopeWithProgress()` removed from AchievementUser

**Search:** `withProgress(` and `scopeWithProgress(`

**Action:** This scope on the `AchievementUser` pivot was unused and has been removed. Replace with `achievementsWithSpecificProgress()`:

```php
// Before (v1)
AchievementUser::withProgress(50);

// After (v2)
$user->achievementsWithSpecificProgress(50)->get();
```

### 5h. `declare(strict_types=1)` added to all files

**Action:** No code change needed in the user's codebase, but all package files now use strict types. If the user was passing incorrect types (e.g. string to int parameters), these will now throw `TypeError` at runtime. The changes in 5e and 5f address the most common cases.

## Step 6: Optionally add HasTiers trait

Ask the user if they want to use the new Tiers feature. If yes, add the trait to the User model:

```php
use LevelUp\Experience\Concerns\HasTiers;

class User extends Authenticatable
{
    use GiveExperience, HasAchievements, HasStreaks, HasTiers;
}
```

If the user does not want tiers, no action is needed — the existing features work without it. The package guards against missing `HasTiers` with `method_exists` checks. To disable tiers entirely, set `TIERS_ENABLED=false` in `.env`.

## Step 7: Final sweep

Run a single grep to catch anything missed:

```
Level::add\s*\((?!.*\[)|->levelUp\(|->deductPoints\(|->incrementAchievementProgress\(|->grantAchievement\(|->getStreakLastActivity\(|scopeWithProgress|withProgress\(
```

Review every match and ensure the corresponding fix from Step 5 has been applied.

## Step 8: Verify

1. Run `php artisan test` — all tests must pass.
2. If tests fail, read the failure output and apply fixes. Common issues:
   - `InvalidArgumentException` from `levelUp()` on non-existent levels — add the missing level or guard the call
   - `Exception` from `deductPoints()` on users without experience — guard with `experience()->exists()`
   - `TypeError` from strict types — cast parameters to correct types
   - `Exception` from `incrementAchievementProgress()` — grant the achievement first
3. Re-run tests until green.
4. Inform the user: "Upgrade to v2 complete. All breaking changes have been applied."
