<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Portable query helpers.
 *
 * `whereLike()` searches a column case-insensitively on every supported driver. MySQL and
 * SQLite compare LIKE case-insensitively already; PostgreSQL does not, and it also refuses
 * to LIKE a json column, so there the column is cast to text and ILIKE is used.
 */
class DatabaseMacros
{
    public static function register(): void
    {
        foreach (['whereLike' => 'where', 'orWhereLike' => 'orWhere'] as $macro => $method) {
            $callback = function (string $column, ?string $term) use ($method) {
                /** @var EloquentBuilder|QueryBuilder $this */
                $term = trim((string) $term);
                if ($term === '') {
                    return $this;
                }
                $driver = $this->getConnection()->getDriverName();
                $value = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

                if ($driver === 'pgsql') {
                    $grammar = $this->getGrammar();

                    return $this->{$method}(DB::raw('cast('.$grammar->wrap($column).' as text)'), 'ilike', $value);
                }

                return $this->{$method}($column, 'like', $value);
            };

            EloquentBuilder::macro($macro, $callback);
            QueryBuilder::macro($macro, $callback);
        }
    }
}
