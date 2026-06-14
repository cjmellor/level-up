<?php

declare(strict_types=1);

namespace LevelUp\Experience\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Contracts\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LevelUp\Experience\Contracts\RankingMetric;
use LevelUp\Experience\Contracts\Windowable;
use LevelUp\Experience\Enums\Period;
use LevelUp\Experience\Exceptions\BoardNotFoundException;
use LevelUp\Experience\Exceptions\MetricDisabledException;
use LevelUp\Experience\Exceptions\MetricNotFoundException;
use LevelUp\Experience\Exceptions\MetricNotWindowableException;
use LevelUp\Experience\Models\Tier;
use LevelUp\Experience\Support\LeaderboardEntry;
use LevelUp\Experience\Support\PeriodRange;

class LeaderboardService
{
    private readonly string $userModel;

    private ?Tier $tier = null;

    private ?RankingMetric $metric = null;

    private ?PeriodRange $range = null;

    /** @var (Closure(Builder): Builder)|null */
    private ?Closure $restriction = null;

    public function __construct()
    {
        $this->userModel = config(key: 'level-up.user.model');
    }

    public function board(string $name): static
    {
        $boards = config()->array(key: 'level-up.leaderboard.boards');
        $declaration = $boards[$name] ?? null;

        throw_unless(is_array($declaration), exception: BoardNotFoundException::forName($name));

        $metric = $declaration['metric'] ?? null;

        throw_unless(is_string($metric), exception: MetricNotFoundException::forBoard($name));

        $resolved = $this->resolveMetric($metric);
        $this->metric = $resolved;

        $tier = $declaration['tier'] ?? null;

        if (is_string($tier)) {
            $this->forTier(tier: $tier);
        }

        $period = $declaration['period'] ?? null;

        if ($period !== null) {
            if (! $resolved instanceof Windowable) {
                $this->resetContext();

                throw MetricNotWindowableException::forBoard(name: $name, metric: $resolved);
            }

            $this->period(period: $period instanceof Period ? $period : Period::from(value: is_string($period) ? $period : ''));
        }

        return $this;
    }

    public function by(string|RankingMetric $metric): static
    {
        $this->metric = $this->resolveMetric($metric);

        return $this;
    }

    public function period(Period $period): static
    {
        return $this->scopeToRange(range: $period->range());
    }

    public function since(CarbonInterface $start, ?CarbonInterface $until = null): static
    {
        $appTimezone = config()->string(key: 'app.timezone');

        return $this->scopeToRange(range: new PeriodRange(
            start: CarbonImmutable::instance(date: $start)->setTimezone(timeZone: $appTimezone),
            end: $until instanceof CarbonInterface ? CarbonImmutable::instance(date: $until)->setTimezone(timeZone: $appTimezone) : null,
        ));
    }

    /**
     * @param  Closure(Builder): Builder  $constraint
     */
    public function restrictTo(Closure $constraint): static
    {
        $this->restriction = $constraint;

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
        [$metric, $tier, $range, $restriction] = $this->consumeContext();

        $query = $this->rankedQuery(metric: $metric, tier: $tier, range: $range, restriction: $restriction)->take(value: $limit);

        return $paginate
            ? $query->paginate()->through(callback: fn (Model $user): LeaderboardEntry => $this->toEntry($user))
            : $query->get()->map(callback: fn (Model $user): LeaderboardEntry => $this->toEntry($user));
    }

    public function rankOf(Model $user): ?int
    {
        [$metric, $tier, $range, $restriction] = $this->consumeContext();

        $rank = DB::query()
            ->fromSub(query: $this->rankedQuery(metric: $metric, tier: $tier, range: $range, restriction: $restriction), as: 'ranked')
            ->where(column: $user->getKeyName(), operator: '=', value: $user->getKey())
            ->value(column: 'rank');

        return $rank === null ? null : (int) $rank;
    }

    public function around(Model $user, int $range): Collection
    {
        [$metric, $tier, $periodRange, $restriction] = $this->consumeContext();

        $ranked = $this->rankedQuery(metric: $metric, tier: $tier, range: $periodRange, restriction: $restriction);
        $grammar = $ranked->getQuery()->getGrammar();

        $positioned = (clone $ranked)->selectRaw(expression: sprintf(
            'ROW_NUMBER() OVER (ORDER BY %s DESC, %s ASC) AS %s',
            $grammar->wrap(value: 'score'),
            $grammar->wrap(value: $user->getKeyName()),
            $grammar->wrap(value: 'position'),
        ));

        $position = DB::query()
            ->fromSub(query: $positioned, as: 'positioned')
            ->where(column: $user->getKeyName(), operator: '=', value: $user->getKey())
            ->value(column: 'position');

        if ($position === null) {
            return new Collection();
        }

        $offset = max(0, (int) $position - 1 - $range);

        return $ranked
            ->skip(value: $offset)
            ->take(value: (int) $position + $range - $offset)
            ->get()
            ->map(callback: fn (Model $user): LeaderboardEntry => $this->toEntry($user))
            ->toBase();
    }

    /**
     * @return array{0: RankingMetric, 1: ?Tier, 2: ?PeriodRange, 3: (Closure(Builder): Builder)|null}
     */
    private function consumeContext(): array
    {
        [$tier, $this->tier] = [$this->tier, null];
        [$range, $this->range] = [$this->range, null];
        [$restriction, $this->restriction] = [$this->restriction, null];
        [$metric, $this->metric] = [$this->metric ?? $this->defaultMetric(), null];

        throw_unless($metric->enabled(), exception: MetricDisabledException::forMetric($metric));

        if ($range instanceof PeriodRange) {
            $this->guardWindowable(metric: $metric);
        }

        return [$metric, $tier, $range, $restriction];
    }

    private function scopeToRange(PeriodRange $range): static
    {
        $metric = $this->metric;

        if ($metric instanceof RankingMetric) {
            $this->guardWindowable(metric: $metric);
        }

        $this->range = $range;

        return $this;
    }

    private function guardWindowable(RankingMetric $metric): void
    {
        if ($metric instanceof Windowable) {
            return;
        }

        $this->resetContext();

        throw MetricNotWindowableException::forMetric($metric);
    }

    private function resetContext(): void
    {
        [$this->metric, $this->tier, $this->range, $this->restriction] = [null, null, null, null];
    }

    private function scoreExpressionFor(RankingMetric $metric, ?PeriodRange $range): BaseBuilder
    {
        return $range instanceof PeriodRange && $metric instanceof Windowable
            ? $metric->windowedScoreExpression(start: $range->start, end: $range->end)
            : $metric->scoreExpression();
    }

    /**
     * @param  (Closure(Builder): Builder)|null  $restriction
     */
    private function rankedQuery(RankingMetric $metric, ?Tier $tier, ?PeriodRange $range, ?Closure $restriction): Builder
    {
        $scored = $this->userModel::query()
            ->select(columns: config(key: 'level-up.user.users_table').'.*')
            ->selectSub(query: $this->scoreExpressionFor(metric: $metric, range: $range), as: 'score')
            ->tap(callback: fn (Builder $query): Builder => $metric->constrain($query))
            ->when($tier instanceof Tier, fn (Builder $query): Builder => $query->whereHas(
                'experience',
                fn (Builder $query): Builder => $query->where(column: 'tier_id', operator: '=', value: $tier->id),
            ));

        if ($restriction instanceof Closure) {
            $scored->tap(callback: $restriction);
        }

        $grammar = $scored->getQuery()->getGrammar();
        $keyName = $scored->getModel()->getKeyName();

        $ranked = $this->userModel::query()
            ->fromSub(query: $scored, as: 'board')
            ->select(columns: 'board.*')
            ->selectRaw(expression: sprintf(
                'RANK() OVER (ORDER BY %s DESC) AS %s',
                $grammar->wrap(value: 'score'),
                $grammar->wrap(value: 'rank'),
            ))
            ->with(relations: ['experience'])
            ->orderByDesc(column: 'score')
            ->orderBy(column: $keyName);

        if ($range instanceof PeriodRange) {
            $ranked->whereNotNull(columns: 'score');
        }

        return $ranked;
    }

    private function toEntry(Model $user): LeaderboardEntry
    {
        $score = $user->getAttribute(key: 'score') + 0;
        $rank = (int) $user->getAttribute(key: 'rank');

        unset($user->score, $user->rank);

        return new LeaderboardEntry(user: $user, score: $score, rank: $rank);
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
