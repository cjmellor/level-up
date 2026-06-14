<?php

declare(strict_types=1);

namespace LevelUp\Experience\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use LevelUp\Experience\Contracts\RankingMetric;
use LevelUp\Experience\Exceptions\MetricDisabledException;
use LevelUp\Experience\Exceptions\MetricNotFoundException;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Support\LeaderboardEntry;

class LeaderboardService
{
    private readonly string $userModel;

    private ?Tier $tier = null;

    private ?RankingMetric $metric = null;

    public function __construct()
    {
        $this->userModel = config(key: 'level-up.user.model');
    }

    public function by(string|RankingMetric $metric): static
    {
        $this->metric = $this->resolveMetric($metric);

        return $this;
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

    public function generate(bool $paginate = false, ?int $limit = null): Collection|LengthAwarePaginator
    {
        [$tier, $this->tier] = [$this->tier, null];
        [$metric, $this->metric] = [$this->metric ?? $this->defaultMetric(), null];

        throw_unless($metric->enabled(), exception: MetricDisabledException::forMetric($metric));

        $users = $this->userModel::query()
            ->select(columns: config(key: 'level-up.user.users_table').'.*')
            ->selectSub(query: $metric->scoreExpression(), as: 'score')
            ->with(relations: ['experience'])
            ->tap(callback: fn (Builder $query): Builder => $metric->constrain($query))
            ->when($tier instanceof Tier, fn (Builder $query): Builder => $query->whereHas(
                'experience',
                fn (Builder $query): Builder => $query->where(column: 'tier_id', operator: '=', value: $tier->id),
            ))
            ->orderByDesc(column: 'score')
            ->take(value: $limit)
            ->when($paginate, fn (Builder $query) => $query->paginate(), fn (Builder $query) => $query->get());

        return $users instanceof LengthAwarePaginator
            ? $users->through(callback: fn (Model $user): LeaderboardEntry => $this->toEntry($user))
            : $users->map(callback: fn (Model $user): LeaderboardEntry => $this->toEntry($user));
    }

    private function toEntry(Model $user): LeaderboardEntry
    {
        $score = $user->getAttribute(key: 'score') + 0;

        unset($user->score);

        return new LeaderboardEntry(user: $user, score: $score);
    }

    private function defaultMetric(): RankingMetric
    {
        return $this->resolveMetric(config(key: 'level-up.leaderboard.default_metric'));
    }

    private function resolveMetric(string|RankingMetric $metric): RankingMetric
    {
        if ($metric instanceof RankingMetric) {
            return $metric;
        }

        $metricClass = config(
            key: "level-up.leaderboard.metrics.{$metric}",
            default: is_subclass_of($metric, RankingMetric::class) ? $metric : null,
        );

        throw_unless($metricClass, exception: MetricNotFoundException::forKey($metric));

        return resolve(name: $metricClass);
    }
}
