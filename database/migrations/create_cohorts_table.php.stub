<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('level-up.tables.cohorts'), function (Blueprint $table) {
            $table->entityId();
            $table->entityForeignId(column: 'division_id')->constrained(table: config('level-up.tables.divisions'));
            $table->timestamp(column: 'period_start');
            $table->timestamp(column: 'period_end');
            $table->timestamp(column: 'rolled_over_at')->nullable();
            $table->timestamps();

            $table->index(['division_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('level-up.tables.cohorts'));
    }
};
