<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LevelUp\Experience\Models\Activity;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(table: 'streak_histories', callback: function (Blueprint $table) {
            $table->id();
            $table->foreignId(column: config(key: 'level-up.user.foreign_key'))->constrained(table: config(key: 'level-up.user.users_table'))->cascadeOnDelete();
            $table->foreignIdFor(model: Activity::class)->constrained(table: 'streak_activities');
            $table->integer(column: 'count')->default(value: 1);
            $table->timestamp(column: 'started_at');
            $table->timestamp(column: 'ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_histories');
    }
};
