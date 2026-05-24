<?php

declare(strict_types=1);

namespace LevelUp\Experience\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\JoinClause;

/**
 * MorphToMany variant that casts the related table's key to text before
 * comparing it against the pivot's polymorphic id column.
 *
 * Postgres rejects implicit comparisons between bigint/uuid and varchar
 * (the type of `multiplier_scopes.scopeable_id`). MySQL and SQLite coerce
 * silently. The cast is only applied on Postgres connections; other drivers
 * use the standard parent join unchanged.
 */
class MorphToManyWithTextCast extends MorphToMany
{
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;

        if ($this->driverName($query) !== 'pgsql') {
            return parent::performJoin($query);
        }

        $relatedKey = $this->related->getTable().'.'.$this->relatedKey;
        $pivotKey = $this->getQualifiedRelatedPivotKeyName();

        $grammar = $query->getQuery()->getGrammar();
        $wrappedRelatedKey = $grammar->wrap($relatedKey);
        $wrappedPivotKey = $grammar->wrap($pivotKey);

        $query->join($this->table, function (JoinClause $join) use ($wrappedPivotKey, $wrappedRelatedKey): void {
            $join->whereRaw("{$wrappedPivotKey} = CAST({$wrappedRelatedKey} AS TEXT)");
        });

        return $this;
    }

    private function driverName(Builder $query): string
    {
        return $query->getQuery()->getConnection()->getDriverName();
    }
}
