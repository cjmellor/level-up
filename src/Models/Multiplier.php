<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Multiplier extends Model
{
    protected $guarded = [];

    protected $casts = [
        'multiplier' => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Multiplier $multiplier): void {
            if ((float) $multiplier->multiplier <= 0) {
                throw new InvalidArgumentException('Multiplier value must be greater than 0.');
            }

            if ($multiplier->starts_at && $multiplier->expires_at && $multiplier->starts_at->gte($multiplier->expires_at)) {
                throw new InvalidArgumentException('starts_at must be before expires_at.');
            }
        });
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(config(key: 'level-up.models.multiplier_scope'));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now())
            )
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now())
            );
    }

    public function scopeForUser(Builder $query, Model $user): Builder
    {
        $tierId = $user->experience?->tier_id;

        return $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('scopes')
            ->orWhereHas('scopes', fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q
                    ->where([
                        'scopeable_type' => $user->getMorphClass(),
                        'scopeable_id' => $user->getKey(),
                    ])
                    ->when($tierId, fn (Builder $q) => $q
                        ->orWhere([
                            'scopeable_type' => app(config(key: 'level-up.models.tier'))->getMorphClass(),
                            'scopeable_id' => $tierId,
                        ])
                    )
                )
            )
        );
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }
}
