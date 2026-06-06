<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

/**
 * @property int|string $id
 * @property string $name
 * @property bool $is_secret
 * @property string|null $description
 * @property string|null $image
 * @property int|string|null $tier_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Achievement extends Model
{
    use HasConfigurableIds, HasFactory, ResolvesConfiguredTable;

    protected $guarded = [];

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.user.model'), table: config('level-up.tables.achievement_user'));
    }

    /**
     * @return BelongsTo<Tier, $this>
     */
    public function tier(): BelongsTo
    {
        /** @var class-string<Tier> $tierClass */
        $tierClass = config(key: 'level-up.models.tier');

        return $this->belongsTo(related: $tierClass);
    }

    protected function configuredTableKey(): string
    {
        return 'achievements';
    }
}
