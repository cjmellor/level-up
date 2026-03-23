<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Challenge extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'conditions' => 'array',
        'rewards' => 'array',
        'auto_enroll' => 'boolean',
        'is_repeatable' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(related: config(key: 'level-up.user.model'), table: 'challenge_user')
            ->using(config(key: 'level-up.models.challenge_user'))
            ->withPivot(columns: ['progress', 'completed_at'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $q): Builder => $q->whereNull(columns: 'starts_at')->orWhere(column: 'starts_at', operator: '<=', value: now()))
            ->where(fn (Builder $q): Builder => $q->whereNull(columns: 'expires_at')->orWhere(column: 'expires_at', operator: '>', value: now()));
    }

    public function scopeAutoEnroll(Builder $query): Builder
    {
        return $query->where(column: 'auto_enroll', operator: '=', value: true);
    }
}
