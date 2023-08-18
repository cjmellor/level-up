<?php

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;

class StreakHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
