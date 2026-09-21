<?php

namespace App\Filament\Resources\ServiceAreas\Pages;

use App\Filament\Resources\ServiceAreas\ServiceAreaResource;
use App\Filament\Concerns\HasTemplateTabs;
use Filament\Resources\Pages\ListRecords;

class ListServiceAreas extends ListRecords
{
    use HasTemplateTabs;

    protected static string $resource = ServiceAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->templateCreateAction(),
        ];
    }
}
