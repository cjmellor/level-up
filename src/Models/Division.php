<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

/**
 * @property int|string $id
 * @property string $name
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Division extends Model
{
    use HasConfigurableIds, ResolvesConfiguredTable;

    protected $guarded = [];

    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * @return HasMany<Cohort, $this>
     */
    public function cohorts(): HasMany
    {
        /** @var class-string<Cohort> $cohortClass */
        $cohortClass = config(key: 'level-up.models.cohort');

        return $this->hasMany(related: $cohortClass, foreignKey: 'division_id');
    }

    protected function configuredTableKey(): string
    {
        return 'divisions';
    }
}
