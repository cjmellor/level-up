<?php

declare(strict_types=1);

namespace LevelUp\Experience\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;
use LevelUp\Experience\Concerns\HasConfigurableIds;
use LevelUp\Experience\Concerns\ResolvesConfiguredTable;

/**
 * @property int|string $id
 * @property string $name
 * @property string|null $description
 * @property string $multiplier
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Multiplier extends Model
{
    use HasConfigurableIds, ResolvesConfiguredTable;

    protected $guarded = [];

    protected $casts = [
        'multiplier' => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsToMany<Model, $this, Pivots\MultiplierUser, 'pivot'>
     */
    public function users(): BelongsToMany
    {
        /** @var class-string<Pivots\MultiplierUser> $pivotClass */
        $pivotClass = config(key: 'level-up.models.multiplier_user');

        return $this->belongsToMany(
            related: config(key: 'level-up.user.model'),
            table: config('level-up.tables.multiplier_user'),
            foreignPivotKey: 'multiplier_id',
            relatedPivotKey: config('level-up.user.foreign_key', 'user_id'),
        )
            ->using($pivotClass)
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Tier, $this, Pivots\MultiplierTier, 'pivot'>
     */
    public function tiers(): BelongsToMany
    {
        /** @var class-string<Tier> $tierClass */
        $tierClass = config(key: 'level-up.models.tier');
        /** @var class-string<Pivots\MultiplierTier> $pivotClass */
        $pivotClass = config(key: 'level-up.models.multiplier_tier');

        return $this->belongsToMany(
            related: $tierClass,
            table: config('level-up.tables.multiplier_tier'),
            foreignPivotKey: 'multiplier_id',
            relatedPivotKey: 'tier_id',
        )
            ->using($pivotClass)
            ->withTimestamps();
    }

    public function scopeToUser(Model ...$users): static
    {
        $this->users()->syncWithoutDetaching(
            collect($users)->map->getKey()->all()
        );

        return $this;
    }

    public function scopeToTier(Tier ...$tiers): static
    {
        $this->tiers()->syncWithoutDetaching(
            collect($tiers)->map->getKey()->all()
        );

        return $this;
    }

    public function unscopeFromUser(Model ...$users): static
    {
        $this->users()->detach(
            collect($users)->map->getKey()->all()
        );

        return $this;
    }

    public function unscopeFromTier(Tier ...$tiers): static
    {
        $this->tiers()->detach(
            collect($tiers)->map->getKey()->all()
        );

        return $this;
    }

    public function isGlobal(): bool
    {
        return ! $this->users()->exists() && ! $this->tiers()->exists();
    }

    protected static function booted(): void
    {
        static::saving(function (Multiplier $multiplier): void {
            throw_if(
                condition: (float) $multiplier->multiplier < 0.01,
                exception: InvalidArgumentException::class,
                message: 'Multiplier value must be at least 0.01.',
            );

            throw_if(
                condition: $multiplier->starts_at && $multiplier->expires_at && $multiplier->starts_at->gte($multiplier->expires_at),
                exception: InvalidArgumentException::class,
                message: 'starts_at must be before expires_at.',
            );
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

        $query->where(fn (Builder $outer) => $outer
            ->where(fn (Builder $global) => $global
                ->whereDoesntHave('users')
                ->whereDoesntHave('tiers'))
            ->orWhereHas('users', fn (Builder $u) => $u->whereKey($user->getKey()))
            ->when($tierId, fn (Builder $q) => $q
                ->orWhereHas('tiers', fn (Builder $t) => $t->whereKey($tierId))
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

    protected function configuredTableKey(): string
    {
        return 'multipliers';
    }
}
