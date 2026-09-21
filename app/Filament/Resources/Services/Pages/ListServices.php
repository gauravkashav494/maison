<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use App\Filament\Concerns\HasTemplateTabs;
use Filament\Resources\Pages\ListRecords;

class ListServices extends ListRecords
{
    use HasTemplateTabs;

    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->templateCreateAction(),
        ];
    }
}
