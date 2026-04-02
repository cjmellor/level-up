<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
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

    public function scopes(): HasMany
    {
        return $this->hasMany(config(key: 'level-up.models.multiplier_scope'));
    }

    public function tiers(): MorphToMany
    {
        return $this->morphedByMany(config(key: 'level-up.models.tier'), 'scopeable', 'multiplier_scopes');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(config(key: 'level-up.user.model'), 'scopeable', 'multiplier_scopes');
    }

    public function scopeTo(Model ...$models): static
    {
        foreach ($models as $model) {
            $this->scopes()->firstOrCreate([
                'scopeable_type' => $model->getMorphClass(),
                'scopeable_id' => $model->getKey(),
            ]);
        }

        return $this;
    }

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

    #[Scope]
    protected function active(Builder $query): void
    {
        $now = now();

        $query
            ->where('is_active', true)
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', $now)
            )
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', $now)
            );
    }

    #[Scope]
    protected function forUser(Builder $query, Model $user): void
    {
        $tierId = $user->experience?->tier_id;

        $query->where(fn (Builder $q) => $q
            ->whereDoesntHave('scopes')
            ->orWhereHas('scopes', fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q
                    ->where([
                        'scopeable_type' => $user->getMorphClass(),
                        'scopeable_id' => $user->getKey(),
                    ])
                    ->when($tierId, fn (Builder $q) => $q
                        ->orWhere([
                            'scopeable_type' => (new (config(key: 'level-up.models.tier')))->getMorphClass(),
                            'scopeable_id' => $tierId,
                        ])
                    )
                )
            )
        );
    }

    #[Scope]
    protected function scheduled(Builder $query): void
    {
        $query
            ->where('is_active', true)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now());
    }

    #[Scope]
    protected function expired(Builder $query): void
    {
        $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }
}
