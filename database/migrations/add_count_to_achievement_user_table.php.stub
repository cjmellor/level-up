<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table(config('level-up.tables.achievement_user'), function (Blueprint $table) {
            $table->unsignedInteger(column: 'count')->nullable()->after('progress');
        });
    }

    public function down(): void
    {
        Schema::table(config('level-up.tables.achievement_user'), function (Blueprint $table) {
            $table->dropColumn('count');
        });
    }
};
