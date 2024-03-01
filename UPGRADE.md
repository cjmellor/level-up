# Upgrade Guide

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
