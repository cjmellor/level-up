<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('experience_audits', function (Blueprint $table) {
            $table->string('type')->change();
        });
    }

    public function down(): void
    {
        Schema::table('experience_audits', function (Blueprint $table) {
            $table->enum('type', ['add', 'remove', 'reset', 'level_up', 'tier_up', 'tier_down'])->change();
        });
    }
};
