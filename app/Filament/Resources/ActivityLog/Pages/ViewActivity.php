<?php

namespace App\Filament\Resources\ActivityLog\Pages;

use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Filament\Support\ActivityTable;
use Filament\Resources\Pages\ViewRecord;

/** One activity line on its own page: the sentence, when and where, and every changed field. */
class ViewActivity extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    public function getTitle(): string
    {
        return 'Activity details';
    }

    protected function getHeaderActions(): array
    {
        return [ActivityTable::backAction(ActivityLogResource::getUrl())];
    }
}
