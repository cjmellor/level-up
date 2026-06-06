<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

/**
 * @property int|string $id
 * @property int|string $user_id
 * @property int|string $level_id
 * @property int $experience_points
 * @property int|string|null $tier_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Experience extends Model
{
    use HasConfigurableIds, ResolvesConfiguredTable;

    protected $guarded = [];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.user.model'));
    }

    /**
     * @return BelongsTo<Level, $this>
     */
    public function status(): BelongsTo
    {
        /** @var class-string<Level> $levelClass */
        $levelClass = config('level-up.models.level');

        return $this->belongsTo(related: $levelClass, foreignKey: 'level_id');
    }

    /**
     * @return BelongsTo<Tier, $this>
     */
    public function tier(): BelongsTo
    {
        /** @var class-string<Tier> $tierClass */
        $tierClass = config('level-up.models.tier');

        return $this->belongsTo(related: $tierClass);
    }

    protected function configuredTableKey(): string
    {
        return 'experiences';
    }
}
