<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('level-up.tables.challenge_completions'), function (Blueprint $table) {
            $table->entityId();
            $table->userForeignId()->constrained(config('level-up.user.users_table'));
            $table->entityForeignId(column: 'challenge_id')->constrained(table: config('level-up.tables.challenges'));
            $table->timestamp(column: 'completed_at');
            $table->timestamps();

            // Serves the leaderboard metric's correlated subqueries, which filter by
            // user first (all-time COUNT(*)) and by user + completed_at range (windowed).
            $table->index([config('level-up.user.foreign_key'), 'completed_at']);
        });

        $this->backfillFromCompletedPivots();
    }

    public function down(): void
    {
        Schema::dropIfExists(config('level-up.tables.challenge_completions'));
    }

    /**
     * Seed one ledger row per challenge_user pivot that is already in a
     * completed state, so existing installs keep their challenge counts
     * when the metric moves from the pivot to the ledger. Models (not raw
     * inserts) are used so ulid/uuid primary keys are generated correctly.
     */
    protected function backfillFromCompletedPivots(): void
    {
        $completionModel = config('level-up.models.challenge_completion');
        $userForeignKey = config('level-up.user.foreign_key');

        DB::table(config('level-up.tables.challenge_user'))
            ->whereNotNull('completed_at')
            ->orderBy('id')
            ->chunk(500, function ($pivots) use ($completionModel, $userForeignKey): void {
                foreach ($pivots as $pivot) {
                    $completionModel::query()->create([
                        $userForeignKey => $pivot->{$userForeignKey},
                        'challenge_id' => $pivot->challenge_id,
                        'completed_at' => $pivot->completed_at,
                    ]);
                }
            });
    }
};
