<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

class Experience extends Model
{
    use HasConfigurableIds, ResolvesConfiguredTable;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.user.model'));
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(related: config('level-up.models.level'), foreignKey: 'level_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(related: config('level-up.models.tier'));
    }

    protected function configuredTableKey(): string
    {
        return 'experiences';
    }
}
