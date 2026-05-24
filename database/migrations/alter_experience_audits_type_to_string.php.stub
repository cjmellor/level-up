<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table(config('level-up.tables.experience_audits'), function (Blueprint $table) {
            $table->string('type')->change();
        });
    }

    public function down(): void
    {
        $table = config('level-up.tables.experience_audits');
        $values = ['add', 'remove', 'reset', 'level_up', 'tier_up', 'tier_down'];

        // Postgres rejects Laravel/Doctrine's `enum().change()` translation
        // (it inlines CHECK into ALTER COLUMN TYPE, which is invalid). Apply
        // the equivalent change as two separate statements on pgsql; other
        // drivers use the standard Blueprint path.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN type TYPE varchar(255)");
            $values = "'".implode("', '", $values)."'";
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_type_check CHECK (type IN ({$values}))");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($values): void {
            $blueprint->enum('type', $values)->change();
        });
    }
};
