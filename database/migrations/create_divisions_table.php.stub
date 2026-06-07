<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('level-up.tables.divisions'), function (Blueprint $table) {
            $table->entityId();
            $table->string(column: 'name')->unique();
            $table->unsignedInteger(column: 'position')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('level-up.tables.divisions'));
    }
};
