<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

/**
 * @property int|string $id
 * @property int|string $user_id
 * @property int|string $activity_id
 * @property int $count
 * @property \Illuminate\Support\Carbon $activity_at
 * @property \Illuminate\Support\Carbon|null $frozen_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Streak extends Model
{
    use HasConfigurableIds, HasFactory, ResolvesConfiguredTable;

    protected $guarded = [];

    protected $casts = [
        'activity_at' => 'datetime',
        'frozen_until' => 'datetime',
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.user.model'));
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        /** @var class-string<Activity> $activityClass */
        $activityClass = config(key: 'level-up.models.activity');

        return $this->belongsTo(related: $activityClass);
    }

    protected function configuredTableKey(): string
    {
        return 'streaks';
    }
}
