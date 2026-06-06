<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

/**
 * @property int|string $id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Activity extends Model
{
    use HasConfigurableIds, HasFactory, ResolvesConfiguredTable;

    protected $guarded = [];

    /**
     * @return HasMany<Streak, $this>
     */
    public function streaks(): HasMany
    {
        /** @var class-string<Streak> $streakClass */
        $streakClass = config(key: 'level-up.models.streak');

        return $this->hasMany(related: $streakClass);
    }

    protected function configuredTableKey(): string
    {
        return 'streak_activities';
    }
}
