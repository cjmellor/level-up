<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string(column: 'name');
            $table->text(column: 'description')->nullable();
            $table->string(column: 'image')->nullable();
            $table->json(column: 'conditions');
            $table->json(column: 'rewards');
            $table->boolean(column: 'auto_enroll')->default(false);
            $table->boolean(column: 'is_repeatable')->default(false);
            $table->timestamp(column: 'starts_at')->nullable();
            $table->timestamp(column: 'expires_at')->nullable();
            $table->json(column: 'metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
