<?php

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Streak extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'activity_at' => 'datetime',
        'frozen_until' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config(key: 'level-up.tables.streaks');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.user.model'));
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(related: Activity::class);
    }
}
