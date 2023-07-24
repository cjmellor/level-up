<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table(config('level-up.user.users_table'), function (Blueprint $table) {
            $table->foreignId('level_id')
                ->after('remember_token')
                ->nullable()
                ->constrained();
        });
    }

    public function down(): void
    {
        Schema::table(config('level-up.user.users_table'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('level_id');
        });
    }
};
