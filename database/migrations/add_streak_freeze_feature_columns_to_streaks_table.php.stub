<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('streaks', function (Blueprint $table) {
            $table->after('activity_at', function (Blueprint $table) {
                $table->timestamp('frozen_until')->nullable();
            });
        });
    }

    public function down(): void
    {
        Schema::table('streaks', function (Blueprint $table) {
            $table->dropColumn('frozen_until');
        });
    }
};
