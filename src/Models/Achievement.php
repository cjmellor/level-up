<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LevelUp\Experience\Concerns\HasConfigurableIds;

class Achievement extends Model
{
    use HasConfigurableIds, HasFactory;

    protected $guarded = [];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.user.model'));
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(related: config(key: 'level-up.models.tier'));
    }
}
