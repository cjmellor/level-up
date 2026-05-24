# Upgrade Guide

## v2.0 -> v2.1

v2.1 is a bug-fix release. No public API changes, no schema changes. Most upgraders need only update Composer; the two fixes below take effect automatically.

### PostgreSQL: Multiplier scoping now works

**Likelihood Of Impact: High** (Postgres users with tier-scoped or user-scoped multipliers); **None** (MySQL, SQLite, or non-scoped multipliers).

Before v2.1, calling `$multiplier->tiers` or `$multiplier->users` on PostgreSQL raised `SQLSTATE[42883]: operator does not exist: bigint = character varying`. The morph pivot column (`multiplier_scopes.scopeable_id`) is varchar, and Postgres refuses to implicitly compare it against the related model's bigint/uuid primary key.

v2.1 ships a Postgres-specific cast in the join clause. No user action required. MySQL and SQLite are unchanged — the cast only activates on `pgsql` connections.

This workaround will be removed in v3, when `multiplier_scopes` is replaced with two typed pivot tables.

### Config: published configs from v1.x are now backfilled

**Likelihood Of Impact: Critical** (users who upgraded from v1.x and published their config before v2.0); **None** (fresh v2.x installs).

Before v2.1, an app that had published `config/level-up.php` under v1.x crashed on `addPoints()` after upgrading to v2.x. The cause: Laravel's `mergeConfigFrom` is a shallow merge, so the v2-era `models` block in the package's bundled config was overridden by its absence in the published file. `config('level-up.models.experience')` returned `null`, and the points-increased listener tried to instantiate a null class.

v2.1 backfills missing config keys with package defaults at runtime. Your published config is still the source of truth for anything you have set; only keys absent from your published config fall back to defaults. No action required.

### Heads-up: feature toggles now default to `true` after upgrade

**Likelihood Of Impact: Medium** (v1.x users upgrading whose published config predates the new feature toggles).

A side-effect of the config fix above: keys like `level-up.tiers.enabled`, `level-up.multipliers.enabled`, and `level-up.challenges.enabled` — which didn't exist in v1.x — now fall back to their bundled defaults, which are `true`. Before v2.1, the shallow-merge bug accidentally made these features look "off" (the keys returned `null`) for upgraders.

If you upgraded from v1.x and **do not** want tiers, multipliers, or challenges enabled, explicitly set them in your published config:

```php
// config/level-up.php
return [
    // ...
    'tiers' => ['enabled' => false],
    'multipliers' => ['enabled' => false],
    'challenges' => ['enabled' => false],
];
```

Fresh v2.x installs are unaffected — their published config already reflects the intended defaults.

### Database compatibility notes

- **PostgreSQL:** now fully supported across all features, including `Multiplier::scopeTo()`. PostgreSQL 14+ recommended.
- **MySQL:** no known limitations. MySQL 8.0+ recommended.
- **SQLite:** supported, but ensure foreign-key enforcement is enabled (`PRAGMA foreign_keys = ON`) — Laravel does this by default, but check your `database.php` connection config if you've customised it.

## v1.x -> v2.0

### Requirements

**Likelihood Of Impact: High**

- **PHP 8.3+** is now required (was PHP 8.1+)
- **Laravel 12 or 13** is now required (was Laravel 10, 11, or 12)

### High Impact Changes

#### Multiplier System Replaced With Database-Backed Model

**Likelihood Of Impact: High**

The class-based multiplier system has been entirely replaced with a database-backed Eloquent model. Multipliers are now managed at runtime via the database — no PHP classes needed.

**New migrations required** — run `php artisan vendor:publish --tag="level-up-migrations"` then `php artisan migrate`:

- `create_multipliers_table` — creates the `multipliers` table
- `create_multiplier_scopes_table` — polymorphic scoping for user/tier targeting
- `add_multipliers_column_to_experience_audits_table` — adds multiplier audit trail

The following have been removed:

- The `Multiplier` contract (`LevelUp\Experience\Contracts\Multiplier`)
- The `MultiplierService` and `MultiplierServiceProvider`
- The `level-up:multiplier` artisan command
- The `withMultiplierData()` method from the `GiveExperience` trait
- The `multiplier.path` and `multiplier.namespace` config keys (replaced with `multiplier.stack_strategy`)
- The `tiers.multipliers` config key (use DB-scoped multipliers instead)

**Migrating from class-based multipliers:**

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

For multipliers with complex `qualifies()` logic, create the DB record and toggle `is_active` programmatically from your application code.

**Migrating from config-based tier multipliers:**

```php
$multiplier = Multiplier::create([
    'name' => 'Gold Tier Bonus',
    'multiplier' => 2,
    'is_active' => true,
]);

$multiplier->tiers()->attach(Tier::where('name', 'Gold')->first());
```

#### `addPoints()` Multiplier Parameter Type Changed

**Likelihood Of Impact: High**

The `$multiplier` parameter on `addPoints()` changed from `?int` to `int|float|null`. This supports fractional multipliers (e.g. `1.5`) but may affect code that strictly type-checks the parameter.

### Medium Impact Changes

#### `levelUp()` Now Throws on Invalid Levels

**Likelihood Of Impact: Medium**

Previously, calling `$user->levelUp(to: 999)` with a non-existent level would silently do nothing. In v2, it throws an `InvalidArgumentException`.

#### `deductPoints()` Now Throws When No Experience Record Exists

**Likelihood Of Impact: Medium**

Previously, calling `$user->deductPoints(50)` on a user with no experience record would silently return. In v2, it throws an `Exception`, matching the behaviour of `setPoints()`.

#### `Level::add()` Only Accepts Arrays

**Likelihood Of Impact: Medium**

The scalar form has been removed. Use the array form instead:

```php
// Before (v1)
Level::add(level: 1, pointsToNextLevel: 100);

// After (v2)
Level::add(['level' => 1, 'next_level_experience' => 100]);
```

### Low Impact Changes

#### `incrementAchievementProgress()` Now Throws When User Lacks the Achievement

**Likelihood Of Impact: Low**

Previously, this would cause a null dereference error. In v2, it throws a clear `Exception` with a helpful message.

#### `grantAchievement()` Progress Parameter Is Now Typed

**Likelihood Of Impact: Low**

The `$progress` parameter is now typed as `?int`. If you were passing non-integer values (e.g. strings), they will now cause a `TypeError` under strict types.

#### `getStreakLastActivity()` Return Type Changed

**Likelihood Of Impact: Low**

The method now returns `?Streak` instead of `Streak`. If you were calling this method directly, you may need to handle the `null` case.

#### `AchievementUser::scopeWithProgress()` Removed

**Likelihood Of Impact: Low**

This scope was unused and has been removed. Use `achievementsWithSpecificProgress()` instead.

### New Features

#### Tiers

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

#### DB-Backed Multipliers

Multipliers can now be created, scheduled, and scoped to users or tiers entirely via the database. See the [Multipliers section in the README](README.md#multipliers) for full usage.

### New: Challenges Feature

v2.0 introduces a Challenges system for multi-condition goals with rewards. Users can enroll in challenges, track progress across multiple conditions (points earned, levels, achievements, streaks, tiers, custom), and earn rewards on completion.

**New migrations required** — run `php artisan vendor:publish --tag="level-up-migrations"` then `php artisan migrate`:

- `create_challenges_table` — creates the `challenges` table
- `create_challenge_user_table` — creates the `challenge_user` pivot table with progress tracking

**Add the `HasChallenges` trait** to your User model to use challenge features:

```php
use LevelUp\Experience\Concerns\HasChallenges;

class User extends Model
{
    use GiveExperience, HasAchievements, HasStreaks, HasTiers, HasChallenges;
}
```

> [!NOTE]
> The `HasChallenges` trait is optional. If you don't add it, the existing features (points, achievements, streaks, tiers) continue to work without challenges. Challenges are evaluated automatically when relevant events fire (PointsIncreased, AchievementAwarded, etc.), but only if the config is enabled and the trait is present.

**To disable challenges entirely**, set `CHALLENGES_ENABLED=false` in your `.env` file.

### New: Configurable Entity ID Type

**Likelihood of Impact: Low**

The package's own primary keys can now be configured as `uuid` or `ulid` via `level-up.entities.id_type` (default remains `bigint`). Existing installs are unaffected on `composer update` — the setting only changes how *new* migrations build their tables. See the [Customizing Identifiers section in the README](README.md#customizing-identifiers) for the conversion path if you want to migrate an existing install.

### New: Configurable Table Names

**Likelihood of Impact: Low**

A new `table_prefix` and `tables` config block lets you rename any of the package's 13 tables. The previous top-level `'table'` key is now deprecated — if you customised it before, it still works as a fallback for `tables.experiences`. No action required; consider migrating to `tables.experiences` on your next config publish. See the [Customizing Table Names section in the README](README.md#customizing-table-names) for examples.

### Bug Fixes Included

- `levelUp()` now correctly fires `UserLevelledUp` events for all intermediate levels (previously only fired for the final level)
- `StreakBroken` event now correctly filters by activity (previously could return the wrong streak)
- `nextLevelAt()` no longer crashes when the current level is missing from the database
- `freezeStreak()` no longer causes a `TypeError` when `STREAK_FREEZE_DURATION` is set via `.env`
- `Level::add()` now catches `UniqueConstraintViolationException` specifically instead of all `Throwable`

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
