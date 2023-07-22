<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('achievement_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId(column: config('level-up.user.foreign_key'))->constrained(config('level-up.user.users_table'));
            $table->foreignId(column: 'achievement_id')->constrained();
            $table->integer(column: 'progress')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievement_user');
    }
};
