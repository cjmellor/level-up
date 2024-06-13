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

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config(key: 'level-up.tables.experience_audits');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.user.model'));
    }
}
