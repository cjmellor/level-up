<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

class Activity extends Model
{
    use HasConfigurableIds, HasFactory, ResolvesConfiguredTable;

    protected $guarded = [];

    public function streaks(): HasMany
    {
        return $this->hasMany(related: config(key: 'level-up.models.streak'));
    }

    protected function configuredTableKey(): string
    {
        return 'streak_activities';
    }
}
