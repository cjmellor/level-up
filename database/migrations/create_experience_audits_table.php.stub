<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('experience_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId(config('level-up.user.foreign_key'))->constrained(config('level-up.user.users_table'));
            $table->integer('points')->index();
            $table->boolean('levelled_up')->default(false);
            $table->integer('level_to')->nullable();
            $table->enum('type', ['add', 'remove', 'reset', 'level_up']);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_audits');
    }
};
