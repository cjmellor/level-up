# Ugrade Guide

## v0.0.6 -> v0.0.7

v0.0.7 comes with a brand-new feature -- Streaks.

Some new configuration settings have been introduced. Delete the `config/level-up.php` file.

Now run `php artisan vendor:publish` and select `LevelUp\Experience\LevelUpServiceProvider`

This also publishes new migration files. Run `php artisan migrate` to migrate the new tables.

Important Note
A new migration is required to accommodate the ended_at column becoming nullable. This migration should alter the table schema to allow NULL values for the ended_at column. Ensure data integrity during this process.

