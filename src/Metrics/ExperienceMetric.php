<?php

declare(strict_types=1);

namespace LevelUp\Experience\Metrics;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use LevelUp\Experience\Contracts\RankingMetric;
use LevelUp\Experience\Contracts\Windowable;
use LevelUp\Experience\Enums\AuditType;
use LevelUp\Experience\Exceptions\MetricRequiresAuditingException;

class ExperienceMetric implements RankingMetric, Windowable
{
    public function key(): string
    {
        return 'xp';
    }

    public function label(): string
    {
        return 'Experience';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function constrain(EloquentBuilder $query): EloquentBuilder
    {
        return $query->whereHas(
            relation: 'experience',
            callback: fn (EloquentBuilder $query): EloquentBuilder => $query->whereNotNull(columns: 'experience_points'),
        );
    }

    public function scoreExpression(): Builder
    {
        $experienceModel = config(key: 'level-up.models.experience');

        return $experienceModel::query()
            ->select(columns: 'experience_points')
            ->whereColumn(
                first: config(key: 'level-up.user.foreign_key'),
                operator: '=',
                second: config(key: 'level-up.user.users_table').'.id',
            )
            ->latest()
            ->take(value: 1)
            ->toBase();
    }

    public function windowedScoreExpression(CarbonInterface $start, ?CarbonInterface $end = null): Builder
    {
        throw_unless(config()->boolean(key: 'level-up.audit.enabled'), exception: MetricRequiresAuditingException::forMetric($this));

        $auditModel = config(key: 'level-up.models.experience_audit');

        $query = $auditModel::query()->toBase();
        $grammar = $query->getGrammar();

        $query
            ->selectRaw(expression: sprintf(
                'SUM(CASE WHEN %s = ? THEN %s ELSE -%s END)',
                $grammar->wrap(value: 'type'),
                $grammar->wrap(value: 'points'),
                $grammar->wrap(value: 'points'),
            ), bindings: [AuditType::Add->value])
            ->whereIn(column: 'type', values: [AuditType::Add->value, AuditType::Remove->value])
            ->whereColumn(
                first: config(key: 'level-up.user.foreign_key'),
                operator: '=',
                second: config(key: 'level-up.user.users_table').'.id',
            )
            ->where(column: 'created_at', operator: '>=', value: $start);

        if ($end instanceof CarbonInterface) {
            $query->where(column: 'created_at', operator: '<', value: $end);
        }

        return $query;
    }
}
