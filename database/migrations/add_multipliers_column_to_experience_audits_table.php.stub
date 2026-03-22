<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('experience_audits', function (Blueprint $table) {
            $table->json('multipliers')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('experience_audits', function (Blueprint $table) {
            $table->dropColumn('multipliers');
        });
    }
};
