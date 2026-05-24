<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('level-up.tables.multiplier_tier'), function (Blueprint $table) {
            $table->entityId();
            $table->entityForeignId('multiplier_id')->constrained(table: config('level-up.tables.multipliers'))->cascadeOnDelete();
            $table->entityForeignId('tier_id')->constrained(table: config('level-up.tables.tiers'))->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['multiplier_id', 'tier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('level-up.tables.multiplier_tier'));
    }
};
