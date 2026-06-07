---
name: level-up-upgrade-v3
description: Automatically upgrade cjmellor/level-up from v2.x to v3.0, applying all breaking changes to the user's codebase.
---

## When to use this skill

Use this skill when a user needs to upgrade from cjmellor/level-up v2.x to v3.0.

If the user is on v1.x, run the `level-up-upgrade-v2` skill first, then come back here.

## What's in v3

The headline is a metric-driven leaderboard: rank by any metric (`xp`, `level`, `streak`, `achievements`, `challenges`, or a custom `RankingMetric`), time Periods, `rankOf()`/`around()`, `restrictTo()` for friends boards, named Boards declared in config, Snapshots with rank-change events, a `leaderboard_rank` challenge condition, and Leagues (a Division ladder with Cohorts, promotion, and relegation). Three leaderboard breaking changes ride along:

- `Leaderboard::generate()` returns `LeaderboardEntry` objects (`$entry->user`, `$entry->score`, `$entry->rank`) instead of a bare collection of `User` models.
- `level-up.audit.enabled` defaults to `true` (was `false`) — periodic leaderboards source their scores from the `experience_audits` ledger.
- Leaderboard ranks are computed with SQL window functions, requiring SQLite 3.25+ or MySQL 8+ (MariaDB 10.2+ and PostgreSQL support them natively).

Plus cleanup:

- Replaces the polymorphic `multiplier_scopes` table with two typed pivots — `multiplier_user` and `multiplier_tier`. Eliminates the need for v2.1's Postgres-specific morph-cast workaround.
- Replaces `Multiplier::scopeTo($model)` with `scopeToUser($user)` / `scopeToTier($tier)` (plus `unscopeFromUser`, `unscopeFromTier`, `isGlobal`).
- Removes the deprecated `'level-up.table'` config key (use `'tables.experiences'`).
- Replaces the `UserForeignKey::on($table)` migration helper with a `$table->userForeignId()` Blueprint macro.
- Removes the incomplete trait method aliasing helpers added in PR #123.
- `setPoints()` now recalculates level and tier (and fires events) instead of just writing the raw column.
- `addPoints()` past the highest level threshold now caps at the top level instead of throwing.
- `addPoints()` is wrapped in `DB::transaction()` for atomicity.
- `revokeAchievement()` clears the cached relation collection so subsequent reads see the detached state.
- Fixes a Postgres rollback bug on `alter_experience_audits_type_to_string`.

PHP and Laravel version requirements are unchanged (PHP 8.3+, Laravel 12 or 13).

## Step 1: Pre-flight checks

1. Confirm the user has backed up their database. The `multiplier_scopes` table will be transformed by a backfill migration — if anything goes wrong, they need a restore path.
2. Confirm the user is on v2.x and not v1.x. If `composer show cjmellor/level-up | grep versions` shows v1.x, redirect to `level-up-upgrade-v2` first.
3. Check the database version if the app uses the leaderboard: ranks need window functions — SQLite 3.25+ or MySQL 8+ (MariaDB 10.2+ and PostgreSQL are fine). MySQL 5.7 cannot run v3 leaderboard queries.
4. Run `php artisan migrate:status` to ensure no pending migrations.
5. Run `php artisan test` to ensure the test suite passes before starting.

## Step 2: Update the composer constraint

Update `composer.json`:

```json
"cjmellor/level-up": "^3.0"
```

Then run `composer update cjmellor/level-up`.

## Step 3: Update application code that uses `Multiplier::scopeTo()`

Search the codebase for `scopeTo(`. For each call, change it to `scopeToUser` or `scopeToTier` depending on the model type:

```php
// Before
$multiplier->scopeTo($user);
$multiplier->scopeTo($goldTier);
$multiplier->scopeTo($user, $goldTier);

// After
$multiplier->scopeToUser($user);
$multiplier->scopeToTier($goldTier);
$multiplier->scopeToUser($user)->scopeToTier($goldTier);
```

If a single `scopeTo` call mixes types — `scopeTo($user, $goldTier)` — split it into one call per type.

If the application uses `$multiplier->scopes()` (the old `HasMany` to `MultiplierScope`), that relation is gone. Use `$multiplier->users` and `$multiplier->tiers` (now `BelongsToMany`) instead, or `$multiplier->isGlobal()` for the "no scopes" check.

## Step 4: Update any direct references to `MultiplierScope`

Search for `MultiplierScope`. The model is removed in v3. Any code instantiating, querying, or type-hinting it needs to migrate to the typed pivot tables (`DB::table('multiplier_user')` / `DB::table('multiplier_tier')` for raw queries).

## Step 5: Update `level-up.table` config (if present)

Open the user's published `config/level-up.php`. If it has:

```php
'table' => 'experiences',
```

…delete that line and add or update the `tables` block:

```php
'tables' => [
    'experiences' => 'experiences', // or whatever value 'table' was set to
    // ... other table overrides
],
```

## Step 6: Update `level-up.tables.multiplier_scopes` config (if present)

If the published config has `'multiplier_scopes' => '...'` under `'tables'`, remove that key and add the two new ones:

```php
'tables' => [
    // ...
    'multiplier_user' => 'multiplier_user',
    'multiplier_tier' => 'multiplier_tier',
    // ...
],
```

If you had a custom name for `multiplier_scopes` (e.g. `'gamification_scopes'`), pick appropriate names for the two new tables (e.g. `'gamification_user'` and `'gamification_tier'`).

## Step 7: Update any custom migrations using `UserForeignKey::on()`

Search the application's migrations for `UserForeignKey`. For each:

```php
// Before
use LevelUp\Experience\Support\UserForeignKey;
// ...
UserForeignKey::on($table)->constrained(config('level-up.user.users_table'))->cascadeOnDelete();

// After
$table->userForeignId()->constrained(config('level-up.user.users_table'))->cascadeOnDelete();
```

Delete the `use LevelUp\Experience\Support\UserForeignKey;` import — the class is gone.

## Step 8: Audit `setPoints()` usage

Search for `setPoints(`. Confirm with the user that the new behavior (recompute level + tier, fire events) is desired at each callsite. If any callsite needs the v2.x raw-write semantics, replace with:

```php
$user->experience->update(['experience_points' => $amount]);
```

## Step 9: Audit `addPoints()` exception handlers

Search for `catch.*Points exceed` or any try/catch around `addPoints` that handles the "Points exceed the last level's experience points" exception. The exception is no longer thrown in v3 — the user is capped at the highest level instead. Remove the catch block, or convert it to a level-cap check.

## Step 10: Trait method collisions

If the host's User model defines any of `challenges()`, `streaks()`, `experience()`, `experienceHistory()`, those methods now collide with the package's trait methods (PR #123's aliasing helpers were removed). The user needs to either:

- Rename their User method (e.g. `userChallenges()`).
- Move the level-up traits onto a separate `UserProfile` / `Gamification` model and compose it.

## Step 11: Update leaderboard consumers

Search for `Leaderboard::`. Every terminal call (`generate()`, `around()`) now returns `LeaderboardEntry` objects instead of `User` models — update code that iterated users directly:

```php
// Before (v2.x)
foreach (Leaderboard::generate() as $user) {
    $user->name;
    $user->experience->experience_points;
}

// After (v3.0)
foreach (Leaderboard::generate() as $entry) {
    $entry->user->name;  // the User model (experience still eager-loaded)
    $entry->score;       // the user's score on the board's metric
    $entry->rank;        // competition rank — tied scores share a rank (1, 1, 3)
}
```

Blade templates, API resources, and tests that consumed the old shape need the same treatment.

## Step 12: Decide on the audit default

`level-up.audit.enabled` now defaults to `true`. Ask the user:

- If they want time-windowed leaderboards (`period()` / `since()` on XP), auditing must stay on — and note that windowed boards only see activity recorded after auditing was enabled.
- If they relied on the old default to keep `experience_audits` empty, set `AUDIT_POINTS=false` in `.env` — but periodic XP boards will then throw `MetricRequiresAuditingException`.
- If their published config pins `'enabled' => env('AUDIT_POINTS', false)`, nothing changes until they update it.

## Step 13: Publish and run migrations

```bash
php artisan vendor:publish --tag="level-up-migrations"
php artisan migrate
```

Publishing picks up the new leaderboard tables: `leaderboard_snapshots`, `divisions`, `cohorts`, and `cohort_user`. The `migrate_multiplier_scopes_to_typed_pivots` migration runs automatically. If the user has existing `multiplier_scopes` data, it'll print a summary like:

```
  level-up: migrated 47 multiplier scope rows (32 user, 15 tier) into typed pivot tables; dropped multiplier_scopes.
```

If the migration encounters rows with a `scopeable_type` it doesn't recognise (not the configured user model and not the configured Tier class), it logs a warning and skips them — those rows are lost. Surface this to the user if it happens.

## Step 14: Run tests

```bash
php artisan test
```

If anything fails, walk back through the steps above — most v3 breakage is straightforward find-and-replace.

## Step 15: Verify

Spot-check that scoped multipliers still apply correctly:

```php
$user = User::find(1);
$multiplier = Multiplier::active()->forUser($user)->first();
dump($multiplier?->name);
```

Done. The user is on v3.
