<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;
use LevelUp\Experience\Enums\AuditType;

/**
 * @property int|string $id
 * @property int|string $user_id
 * @property int $points
 * @property bool $levelled_up
 * @property int|null $level_to
 * @property AuditType $type
 * @property string|null $reason
 * @property array|null $multipliers
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ExperienceAudit extends Model
{
    use HasConfigurableIds, ResolvesConfiguredTable;

    protected $guarded = [];

    protected $casts = [
        'type' => AuditType::class,
        'multipliers' => 'array',
    ];

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config(key: 'level-up.user.model'));
    }

    protected function configuredTableKey(): string
    {
        return 'experience_audits';
    }
}
