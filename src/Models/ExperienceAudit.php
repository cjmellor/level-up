<?php

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LevelUp\Experience\Enums\AuditType;

class ExperienceAudit extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => AuditType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.user.model'));
    }
}
