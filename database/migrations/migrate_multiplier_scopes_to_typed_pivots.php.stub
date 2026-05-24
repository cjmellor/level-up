<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $oldTable = config('level-up.tables.multiplier_scopes', 'multiplier_scopes');

        // Fresh v3 install — old morph table never existed. Nothing to do.
        if (! Schema::hasTable($oldTable)) {
            return;
        }

        $userTable = config('level-up.tables.multiplier_user');
        $tierTable = config('level-up.tables.multiplier_tier');
        $userKey = config('level-up.user.foreign_key', 'user_id');
        $userMorph = (new (config('level-up.user.model')))->getMorphClass();
        $tierMorph = (new (config('level-up.models.tier')))->getMorphClass();

        $userInserts = 0;
        $tierInserts = 0;

        DB::transaction(function () use ($oldTable, $userTable, $tierTable, $userKey, $userMorph, $tierMorph, &$userInserts, &$tierInserts): void {
            DB::table($oldTable)->orderBy('id')->chunkById(500, function ($rows) use ($userTable, $tierTable, $userKey, $userMorph, $tierMorph, &$userInserts, &$tierInserts): void {
                $userBatch = [];
                $tierBatch = [];

                foreach ($rows as $row) {
                    $base = [
                        'multiplier_id' => $row->multiplier_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];

                    if ($row->scopeable_type === $userMorph) {
                        $userBatch[] = $base + [$userKey => $row->scopeable_id];
                    } elseif ($row->scopeable_type === $tierMorph) {
                        $tierBatch[] = $base + ['tier_id' => $row->scopeable_id];
                    } else {
                        Log::warning("level-up: skipping unknown scopeable_type [{$row->scopeable_type}] in {$oldTable}#{$row->id}");
                    }
                }

                if ($userBatch !== []) {
                    DB::table($userTable)->insertOrIgnore($userBatch);
                    $userInserts += count($userBatch);
                }

                if ($tierBatch !== []) {
                    DB::table($tierTable)->insertOrIgnore($tierBatch);
                    $tierInserts += count($tierBatch);
                }
            });
        });

        // Drop the old polymorphic table now that data is migrated.
        Schema::drop($oldTable);

        $total = $userInserts + $tierInserts;
        if ($total > 0) {
            echo "  level-up: migrated {$total} multiplier scope rows ({$userInserts} user, {$tierInserts} tier) into typed pivot tables; dropped {$oldTable}.".PHP_EOL;
        }
    }

    public function down(): void
    {
        $oldTable = config('level-up.tables.multiplier_scopes', 'multiplier_scopes');

        if (Schema::hasTable($oldTable)) {
            return;
        }

        $userTable = config('level-up.tables.multiplier_user');
        $tierTable = config('level-up.tables.multiplier_tier');
        $userKey = config('level-up.user.foreign_key', 'user_id');
        $userMorph = (new (config('level-up.user.model')))->getMorphClass();
        $tierMorph = (new (config('level-up.models.tier')))->getMorphClass();

        Schema::create($oldTable, function ($table) {
            $table->entityId();
            $table->entityForeignId('multiplier_id')->constrained(table: config('level-up.tables.multipliers'))->cascadeOnDelete();
            $table->string('scopeable_type');
            $table->string('scopeable_id');
            $table->timestamps();
            $table->unique(['multiplier_id', 'scopeable_type', 'scopeable_id'], 'multiplier_scopes_unique');
            $table->index(['scopeable_type', 'scopeable_id']);
        });

        DB::transaction(function () use ($oldTable, $userTable, $tierTable, $userKey, $userMorph, $tierMorph): void {
            DB::table($userTable)->orderBy('id')->chunkById(500, function ($rows) use ($oldTable, $userKey, $userMorph): void {
                $batch = [];
                foreach ($rows as $row) {
                    $batch[] = [
                        'multiplier_id' => $row->multiplier_id,
                        'scopeable_type' => $userMorph,
                        'scopeable_id' => (string) $row->{$userKey},
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }
                if ($batch !== []) {
                    DB::table($oldTable)->insertOrIgnore($batch);
                }
            });

            DB::table($tierTable)->orderBy('id')->chunkById(500, function ($rows) use ($oldTable, $tierMorph): void {
                $batch = [];
                foreach ($rows as $row) {
                    $batch[] = [
                        'multiplier_id' => $row->multiplier_id,
                        'scopeable_type' => $tierMorph,
                        'scopeable_id' => (string) $row->tier_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ];
                }
                if ($batch !== []) {
                    DB::table($oldTable)->insertOrIgnore($batch);
                }
            });
        });

        Schema::dropIfExists($userTable);
        Schema::dropIfExists($tierTable);
    }
};
