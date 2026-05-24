<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('level-up.tables.achievement_user'), function (Blueprint $table) {
            $table->entityId();
            $table->userForeignId()->constrained(config('level-up.user.users_table'));
            $table->entityForeignId(column: 'achievement_id')->constrained(table: config('level-up.tables.achievements'));
            $table->integer(column: 'progress')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('level-up.tables.achievement_user'));
    }
};
