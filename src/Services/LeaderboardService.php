<?php

declare(strict_types=1);

namespace LevelUp\Experience\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use LevelUp\Experience\Models\Experience;
use LevelUp\Experience\Models\Tier;

class LeaderboardService
{
    private readonly string $userModel;

    private ?Tier $tier = null;

    public function __construct()
    {
        $this->userModel = config(key: 'level-up.user.model');
    }

    public function forTier(string|Tier $tier): static
    {
        if (is_string($tier)) {
            $tierClass = config(key: 'level-up.models.tier');
            $tier = $tierClass::where(column: 'name', operator: '=', value: $tier)->firstOrFail();
        }

        $this->tier = $tier;

        return $this;
    }

    public function generate(bool $paginate = false, ?int $limit = null): array|Collection|LengthAwarePaginator
    {
        [$tier, $this->tier] = [$this->tier, null];

        return $this->userModel::query()
            ->with(relations: ['experience'])
            ->whereHas('experience', function (Builder $query) use ($tier): void {
                $query->whereNotNull(columns: 'experience_points');

                if ($tier) {
                    $query->where(column: 'tier_id', operator: '=', value: $tier->id);
                }
            })
            ->orderByDesc(
                column: Experience::query()->select('experience_points')
                    ->whereColumn(config('level-up.user.foreign_key'), config('level-up.user.users_table').'.id')
                    ->latest()
            )
            ->take($limit)
            ->when($paginate, fn (Builder $query) => $query->paginate(), fn (Builder $query) => $query->get());
    }
}
