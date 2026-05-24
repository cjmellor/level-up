<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(table: config('level-up.tables.streak_histories'), callback: function (Blueprint $table) {
            $table->entityId();
            $table->userForeignId()->constrained(table: config(key: 'level-up.user.users_table'))->cascadeOnDelete();
            $table->entityForeignId(column: 'activity_id')->constrained(table: config('level-up.tables.streak_activities'));
            $table->integer(column: 'count')->default(value: 1);
            $table->timestamp(column: 'started_at');
            $table->timestamp(column: 'ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('level-up.tables.streak_histories'));
    }
};
