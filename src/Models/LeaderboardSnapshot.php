<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

/**
 * @property int|string $id
 * @property string $board
 * @property int|string $user_id
 * @property int $rank
 * @property float $score
 * @property \Illuminate\Support\Carbon $run_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class LeaderboardSnapshot extends Model
{
    use HasConfigurableIds, ResolvesConfiguredTable;

    protected $guarded = [];

    protected $casts = [
        'rank' => 'integer',
        'score' => 'float',
        'run_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.user.model'));
    }

    protected function configuredTableKey(): string
    {
        return 'leaderboard_snapshots';
    }
}
