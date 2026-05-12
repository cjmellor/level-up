<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

class Streak extends Model
{
    use HasConfigurableIds, HasFactory, ResolvesConfiguredTable;

    protected $guarded = [];

    protected $casts = [
        'activity_at' => 'datetime',
        'frozen_until' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.user.model'));
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(related: config(key: 'level-up.models.activity'));
    }

    protected function configuredTableKey(): string
    {
        return 'streaks';
    }
}
