<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('level-up.tables.leaderboard_snapshots'), function (Blueprint $table) {
            $table->entityId();
            $table->string(column: 'board');
            $table->userForeignId()->constrained(config('level-up.user.users_table'));
            $table->unsignedInteger(column: 'rank');
            $table->double(column: 'score');
            $table->timestamp(column: 'run_at');
            $table->timestamps();

            $table->index(['board', 'run_at']);
            $table->index('run_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('level-up.tables.leaderboard_snapshots'));
    }
};
