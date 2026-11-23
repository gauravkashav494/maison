<?php

namespace App\Filament\Resources\PlatformActivities\Pages;

use App\Filament\Resources\PlatformActivities\PlatformActivityResource;
use App\Filament\Support\ActivityTable;
use Filament\Resources\Pages\ViewRecord;

/** One activity line on its own page: the sentence, when and where, and every changed field. */
class ViewPlatformActivity extends ViewRecord
{
    protected static string $resource = PlatformActivityResource::class;

    public function getTitle(): string
    {
        return 'Activity details';
    }

    protected function getHeaderActions(): array
    {
        return [ActivityTable::backAction(PlatformActivityResource::getUrl())];
    }
}
