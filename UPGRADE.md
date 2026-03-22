# Upgrade Guide

## v1.x -> v2.0

### Requirements

- **PHP 8.3+** is now required (was PHP 8.1+)
- **Laravel 12 or 13** is now required (was Laravel 10, 11, or 12)

### Breaking Changes

#### `levelUp()` now throws on invalid levels

Previously, calling `$user->levelUp(to: 999)` with a non-existent level would silently do nothing. In v2, it throws an `InvalidArgumentException`.

#### `deductPoints()` now throws when no experience record exists

Previously, calling `$user->deductPoints(50)` on a user with no experience record would silently return. In v2, it throws an `Exception`, matching the behaviour of `setPoints()`.

#### `Level::add()` only accepts arrays

The scalar form `Level::add(level: 1, pointsToNextLevel: 100)` has been removed. Use the array form instead:

```php
// Before (v1)
Level::add(level: 1, pointsToNextLevel: 100);

// After (v2)
Level::add(['level' => 1, 'next_level_experience' => 100]);
```

#### `incrementAchievementProgress()` now throws when user lacks the achievement

Previously, calling this method on an achievement the user didn't have would cause a null dereference error. In v2, it throws a clear `Exception` with a helpful message.

#### `grantAchievement()` progress parameter is now typed

The `$progress` parameter is now typed as `?int`. If you were passing non-integer values (e.g. strings), they will now cause a `TypeError` under strict types.

#### `getStreakLastActivity()` return type changed

The method now returns `?Streak` instead of `Streak`. If you were calling this method directly, you may need to handle the `null` case.

#### `AchievementUser::scopeWithProgress()` removed

This scope was unused and has been removed. If you were using it, use `achievementsWithSpecificProgress()` instead.

### Bug Fixes Included

- `levelUp()` now correctly fires `UserLevelledUp` events for all intermediate levels (previously only fired for the final level)
- `StreakBroken` event now correctly filters by activity (previously could return the wrong streak)
- `nextLevelAt()` no longer crashes when the current level is missing from the database
- `freezeStreak()` no longer causes a `TypeError` when `STREAK_FREEZE_DURATION` is set via `.env`
- `Level::add()` now catches `UniqueConstraintViolationException` specifically instead of all `Throwable`

### New: Tiers Feature

v2.0 introduces a Tiers system for named status brackets (e.g. Bronze, Silver, Gold). Tiers are **enabled by default**.

**New migrations required** — run `php artisan vendor:publish --tag="level-up-migrations"` then `php artisan migrate`:

- `create_tiers_table` — creates the `tiers` table
- `add_tier_id_to_experiences_table` — adds `tier_id` foreign key to experiences
- `add_tier_id_to_achievements_table` — adds `tier_id` foreign key to achievements
- `alter_experience_audits_type_to_string` — converts the `type` column from `enum` to `string` (required for new `tier_up`/`tier_down` audit types)

**Add the `HasTiers` trait** to your User model to use tier features:

```php
use LevelUp\Experience\Concerns\HasTiers;

class User extends Model
{
    use GiveExperience, HasAchievements, HasStreaks, HasTiers;
}
```

> [!NOTE]
> The `HasTiers` trait is optional. If you don't add it, the existing features (points, achievements, streaks) continue to work without tiers. The package guards against missing `HasTiers` with `method_exists` checks.

**To disable tiers entirely**, set `TIERS_ENABLED=false` in your `.env` file.

### New: DB-Backed Multiplier System

The class-based multiplier system has been replaced with a database-backed Eloquent model. Multipliers are now managed at runtime via the database — no PHP classes needed.

**New migrations required** — run `php artisan vendor:publish --tag="level-up-migrations"` then `php artisan migrate`:

- `create_multipliers_table` — creates the `multipliers` table
- `create_multiplier_scopes_table` — polymorphic scoping for user/tier targeting
- `add_multipliers_column_to_experience_audits_table` — adds multiplier audit trail

**Breaking changes:**

- The `Multiplier` contract (`LevelUp\Experience\Contracts\Multiplier`) has been removed
- The `MultiplierService` and `MultiplierServiceProvider` have been removed
- The `level-up:multiplier` artisan command has been removed
- The `withMultiplierData()` method has been removed from the `GiveExperience` trait
- The `multiplier.path` and `multiplier.namespace` config keys have been replaced with `multiplier.stack_strategy`
- The `tiers.multipliers` config key has been removed (use DB-scoped multipliers instead)
- The `addPoints()` `multiplier` parameter type changed from `?int` to `int|float|null`

**Migrating from class-based multipliers:**

If you had class-based multipliers (e.g. `app/Multipliers/IsMonthDecember.php`), replace them with database records:

```php
// Before (v1): PHP class with qualifies() logic
// After (v2): Database record
Multiplier::create([
    'name' => 'December Holiday Bonus',
    'multiplier' => 2,
    'is_active' => true,
    'starts_at' => '2026-12-01',
    'expires_at' => '2026-12-31',
]);
```

For multipliers that had complex `qualifies()` logic (checking user state, external APIs, etc.), create the DB record and toggle `is_active` programmatically from your application code.

**Migrating from config-based tier multipliers:**

If you used the `tiers.multipliers` config (e.g. `['Gold' => 2]`), create DB multipliers scoped to tiers instead:

```php
$multiplier = Multiplier::create([
    'name' => 'Gold Tier Bonus',
    'multiplier' => 2,
    'is_active' => true,
]);

$multiplier->tiers()->attach(Tier::where('name', 'Gold')->first());
```

### Other Changes

- `declare(strict_types=1)` has been added to all PHP files
- All source files have been modernised with Rector (PHP 8.3 rules) and Pint (Laravel preset)

## v1.2.3 -> v1.2.4

#### New Migration to remove `level_id` column from `users` table

The `level_id` column in the `users` table has been removed as it was no longer relevant. Run `php artisan vendor:publish --tag=level-up-migrations` to publish the new migration files. 

## v1.2.2 -> v1.2.3

#### New Migration for Nullable `ended_at` Column

In version `v1.2.3`, a new migration is introduced to make the `ended_at` column in the `streak_histories` table nullable. This change allows flexibility in recording the end time of streaks.

To apply this migration:

1. Locate the migration file responsible for creating the `streak_histories` table. This file should be named something like `YYYY_MM_DD_HHMMSS_create_streak_histories_table.php`.

2. Open the migration file and add the following code inside the `up` method:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streak_histories', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('streak_histories', function (Blueprint $table) {
            $table->dropColumn('ended_at');
        });
    }
};
```

## v0.0.6 -> v0.0.7

v0.0.7 comes with a brand-new feature -- Streaks.

Some new configuration settings have been introduced. Delete the `config/level-up.php` file.

Now run `php artisan vendor:publish` and select `LevelUp\Experience\LevelUpServiceProvider`

This also publishes new migration files. Run `php artisan migrate` to migrate the new tables.
