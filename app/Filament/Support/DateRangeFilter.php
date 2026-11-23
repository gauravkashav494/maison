<?php

namespace App\Filament\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter as PluginDateRangeFilter;

/**
 * A date-range filter: one field that opens a calendar with ready ranges (Today, Yesterday,
 * Last 7 days, This month…) or any two days picked by hand. Every "from / until" date filter
 * in the panels uses it, so they all look, read and filter the same way.
 *
 * The range is inclusive of both days and compares calendar dates, so a late-evening row
 * still counts for its own day. The picker comes from malzariey/filament-daterangepicker-filter,
 * whose script and styles are published with `php artisan filament:assets`.
 */
class DateRangeFilter extends PluginDateRangeFilter
{
    /**
     * @param  (Closure(Builder, string $from, string $until): mixed)|null  $scope  filters by the two Y-m-d dates
     *                                                                              (a model scope); by default the dates of $column
     */
    public static function range(string $name, string $label, string $column = 'created_at', ?Closure $scope = null, bool $pastOnly = true): static
    {
        return static::make($name)
            ->label($label)
            ->placeholder('Any date')
            ->format('d/m/Y')
            ->maxDate($pastOnly ? fn () => today() : null)
            ->modifyQueryUsing(function (Builder $query, ?Carbon $startDate, ?Carbon $endDate) use ($column, $scope) {
                if (! $startDate || ! $endDate) {
                    return $query;
                }

                return $scope
                    ? $scope($query, $startDate->toDateString(), $endDate->toDateString())
                    : $query->whereDate($query->qualifyColumn($column), '>=', $startDate->toDateString())
                        ->whereDate($query->qualifyColumn($column), '<=', $endDate->toDateString());
            });
    }
}
