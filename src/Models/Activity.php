<?php

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('level-up.tables.streak_activities') ?: parent::getTable();
    }

    protected $guarded = [];

    public function streaks(): HasMany
    {
        return $this->hasMany(related: Streak::class);
    }
}
