<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('multiplier_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('multiplier_id')->constrained()->cascadeOnDelete();
            $table->string('scopeable_type');
            $table->unsignedBigInteger('scopeable_id');
            $table->timestamps();

            $table->unique(['multiplier_id', 'scopeable_type', 'scopeable_id'], 'multiplier_scopes_unique');
            $table->index(['scopeable_type', 'scopeable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multiplier_scopes');
    }
};
