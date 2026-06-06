# Upgrade Guide

## v2.x -> v3.0

v3.0 is a breaking-change release. Most users only need to run `composer require cjmellor/level-up:^3.0` then `php artisan migrate` — the multiplier schema reshape is backfilled automatically. Application code touching `Multiplier::scopeTo()` needs updating; the legacy `'table'` config key and the `UserForeignKey::on()` migration helper are gone.

The boost skill `level-up-upgrade-v3` walks an LLM through this upgrade interactively if you're using boost.

### Requirements

**Likelihood Of Impact: None** — no PHP or Laravel version bump in v3. Same requirements as v2.x (PHP 8.3+, Laravel 12 or 13).

### `Multiplier::scopeTo()` replaced with typed methods

**Likelihood Of Impact: High** (if you use `Multiplier::scopeTo()` anywhere in app code); **None** (if you only use unscoped/global multipliers).

The polymorphic `scopeTo(Model ...$models)` API is removed. Replace it with the typed equivalents:

```php
// Before (v2.x)
$multiplier->scopeTo($user);
$multiplier->scopeTo($goldTier);
$multiplier->scopeTo($user, $goldTier);

// After (v3.0)
$multiplier->scopeToUser($user);
$multiplier->scopeToTier($goldTier);
$multiplier->scopeToUser($user)->scopeToTier($goldTier);
```

New companion methods: `unscopeFromUser(...)`, `unscopeFromTier(...)`, `isGlobal()`.

The `users()` and `tiers()` relations are now standard `belongsToMany` (not `morphedByMany`). Direct Eloquent calls (`$multiplier->users()->attach($u)`) still work but bypass the idempotency that `scopeToUser` adds — prefer the convenience methods.

### `multiplier_scopes` table replaced with `multiplier_user` + `multiplier_tier`

**Likelihood Of Impact: Critical** (everyone) but **automatic** — the bundled migration `migrate_multiplier_scopes_to_typed_pivots` runs as part of `php artisan migrate` and reads any existing morph rows in chunks of 500, splits them into the new typed pivots by `scopeable_type`, and drops the old table. Prints a summary to stdout:

```
  level-up: migrated 47 multiplier scope rows (32 user, 15 tier) into typed pivot tables; dropped multiplier_scopes.
```

If your published config customised `'tables.multiplier_scopes'`, that config key is no longer read — replace it with `'tables.multiplier_user'` and `'tables.multiplier_tier'`. The `MultiplierScope` model and the `'level-up.models.multiplier_scope'` config key are also removed.

If you have application code querying `multiplier_scopes` directly (raw `DB::table('multiplier_scopes')` calls or custom relations), you need to migrate it to the two typed tables before running `php artisan migrate` in production.

### `'table'` config key removed

**Likelihood Of Impact: Medium** (anyone whose published config still has `'table' => 'experiences'`).

The top-level `'level-up.table'` key was deprecated in v2.0 in favour of `'level-up.tables.experiences'`. v3 removes it entirely.

```php
// Before (in your published config/level-up.php)
'table' => 'my_experiences',

// After
'tables' => [
    'experiences' => 'my_experiences',
    // ... other table overrides
],
```

### `UserForeignKey::on()` replaced by `$table->userForeignId()` macro

**Likelihood Of Impact: Low** (only affects you if you copied the `UserForeignKey` import + call pattern into your own application migrations).

```php
// Before
use LevelUp\Experience\Support\UserForeignKey;
// ...
UserForeignKey::on($table)->constrained(config('level-up.user.users_table'))->cascadeOnDelete();

// After
$table->userForeignId()->constrained(config('level-up.user.users_table'))->cascadeOnDelete();
```

The macro reads `level-up.user.foreign_key_type` from config and routes to `foreignId()` / `foreignUuid()` / `foreignUlid()` — identical behavior, fluent syntax, matches the existing `entityForeignId()` macro.

### Trait method aliasing helpers removed

**Likelihood Of Impact: Low** (only affects v2.x dev-branch users who adopted the `*Relation()` private helpers from PR #123 before they were reverted in v2.1.0).

Internal trait helpers like `experienceRelation()`, `challengesRelation()`, `streaksRelation()`, and `loadedExperience()` were removed. v3 treats trait method names as part of the public API. If your User model defines a colliding `challenges()`, `streaks()`, `experience()`, or `experienceHistory()` method:

- Rename your method to avoid the collision (e.g. `userChallenges()`), or
- Move the level-up traits onto a separate `UserProfile` / `Gamification` model and compose it onto User (same pattern Spatie's permission package uses).

### Auditing is now enabled by default

**Likelihood Of Impact: Medium** (every install that never published the config or never set `AUDIT_POINTS`).

`level-up.audit.enabled` now defaults to `true` (was `false`). v3's time-windowed leaderboards (`Leaderboard::period(...)` / `since(...)`) compute scores from the `experience_audits` ledger, so auditing is on out of the box — every `addPoints()` / `deductPoints()` call writes one `experience_audits` row.

- If you have a **published config** with an explicit `'enabled' => env('AUDIT_POINTS', false)`, nothing changes until you update it — but periodic leaderboards will throw `MetricRequiresAuditingException` until you enable auditing.
- If you relied on the old default to keep the audit table empty, set `AUDIT_POINTS=false` in your `.env`.
- Audit rows only accrue from the moment auditing is on. A windowed board only sees activity recorded in the ledger, so "this week" boards are accurate one week after enabling.

### `setPoints()` now recalculates level and tier

**Likelihood Of Impact: Medium** (silent behavior change for anyone using `setPoints`).

```php
$user->setPoints(2500);
```

In v2.x this wrote the raw column with no side effects. In v3:

- Level is recomputed based on the new point total. If the user moved up, `UserLevelledUp` fires for each crossed level. If they moved down, `level_id` is updated silently (no demotion event in the package).
- Tier is recomputed via `Tier::forPoints($amount)`. If the user moved tier, `UserTierUpdated` fires with `TierDirection::Promoted` or `Demoted`.
- The returned `Experience` model is refreshed so the caller sees the updated `level_id` / `tier_id`.

If you previously relied on `setPoints` not firing events, dispatch the points write directly: `$user->experience->update(['experience_points' => $amount])`.

### `revokeAchievement()` clears the cached relation

**Likelihood Of Impact: Low** (subtle improvement — most callers will benefit).

After `revokeAchievement`, the cached `$user->achievements` collection no longer contains the detached row without needing a manual `$user->refresh()`. This was a footgun in the README's example flow and is fixed.

### Excess points cap at the top level instead of throwing

**Likelihood Of Impact: Medium** (changes a previously-thrown error into success).

```php
// Level ladder ends at level 5 with next_level_experience = 600.
$user->addPoints(1000);

// Before: throws Exception('Points exceed the last level's experience points.')
// After:  user is at level 5 with 1000 points
```

If you were catching that exception, you can stop — it no longer fires.

### `addPoints()` is now transactional

**Likelihood Of Impact: Low** (internal robustness; visible only when synchronous listeners throw).

The mutation phase of `addPoints` is wrapped in `DB::transaction()`. Synchronous listener exceptions on `PointsIncreased` or `UserLevelledUp` now roll back the partial points write atomically — previously they left stale state behind. Queued listeners (`ShouldQueue`) are unaffected.

### Postgres rollback fix for `alter_experience_audits_type_to_string`

**Likelihood Of Impact: None** unless you're rolling back that specific migration on Postgres.

The migration's `down()` previously generated invalid Postgres syntax (`ALTER COLUMN ... TYPE varchar(255) check (...)`). Fixed by emitting `ALTER COLUMN TYPE` and `ADD CONSTRAINT` as separate statements on `pgsql` connections; MySQL and SQLite continue using the standard Blueprint path.

### Database compatibility

- **PostgreSQL:** fully supported across all features, natively. The `MorphToManyWithTextCast` workaround from v2.1 is gone — schema is now Postgres-native.
- **MySQL:** no behaviour change vs v2.1.
- **SQLite:** ensure foreign-key enforcement is enabled (`PRAGMA foreign_keys = ON`) — Laravel's default does this, but verify if you've customised your `database.php` connection config.

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
