<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * For resources of soft-deleting models: an archived record can still be opened (to view,
 * restore or delete it permanently). Lists keep hiding them unless the "Deleted items" filter asks.
 */
trait OpensTrashedRecords
{
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
