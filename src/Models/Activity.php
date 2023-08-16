<?php

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    use HasFactory;

    protected $table = 'streak_activities';

    protected $guarded = [];

    public function streaks(): HasMany
    {
        return $this->hasMany(related: Streak::class);
    }
}
