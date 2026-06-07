<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

/**
 * @property int|string $id
 * @property int|string $division_id
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $period_end
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Division $division
 */
class Cohort extends Model
{
    use HasConfigurableIds, ResolvesConfiguredTable;

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        /** @var class-string<Division> $divisionClass */
        $divisionClass = config(key: 'level-up.models.division');

        return $this->belongsTo(related: $divisionClass, foreignKey: 'division_id');
    }

    /**
     * @return BelongsToMany<Model, $this, Pivots\CohortUser, 'pivot'>
     */
    public function users(): BelongsToMany
    {
        /** @var class-string<Model> $userClass */
        $userClass = config(key: 'level-up.user.model');

        /** @var class-string<Pivots\CohortUser> $pivotClass */
        $pivotClass = config(key: 'level-up.models.cohort_user');

        return $this->belongsToMany(related: $userClass, table: config('level-up.tables.cohort_user'))
            ->using($pivotClass)
            ->withTimestamps();
    }

    protected function configuredTableKey(): string
    {
        return 'cohorts';
    }
}
