<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('challenge_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId(column: config('level-up.user.foreign_key'))->constrained(config('level-up.user.users_table'));
            $table->foreignId(column: 'challenge_id')->constrained();
            $table->json(column: 'progress')->nullable();
            $table->timestamp(column: 'completed_at')->nullable();
            $table->timestamps();

            $table->unique([config('level-up.user.foreign_key'), 'challenge_id']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_user');
    }
};
