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

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config(key: 'level-up.tables.streak_history');
    }
}
