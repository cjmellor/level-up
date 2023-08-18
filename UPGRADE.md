# Ugrade Guide

## v0.0.6 -> v0.0.7

v0.0.7 comes with a brand-new feature -- Streaks.

Some new configuration settings have been introduced. Delete the `config/level-up.php` file.

Now run `php artisan vendor:publish` and select `LevelUp\Experience\LevelUpServiceProvider`

This also publishes new migration files. Run `php artisan migrate` to migrate the new tables.
