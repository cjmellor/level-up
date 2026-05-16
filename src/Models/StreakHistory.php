<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use LevelUp\Experience\Concerns\HasConfigurableIds;

class StreakHistory extends Model
{
    use HasConfigurableIds;

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}
