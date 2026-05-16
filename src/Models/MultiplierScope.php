<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LevelUp\Experience\Concerns\HasConfigurableIds;

class MultiplierScope extends Model
{
    use HasConfigurableIds;

    protected $guarded = [];

    public function multiplier(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.models.multiplier'));
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }
}
