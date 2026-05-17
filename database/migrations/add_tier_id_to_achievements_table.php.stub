<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table(config('level-up.tables.achievements'), function (Blueprint $table) {
            $table->entityForeignId('tier_id')->nullable()->constrained(table: config('level-up.tables.tiers'))->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(config('level-up.tables.achievements'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('tier_id');
        });
    }
};
