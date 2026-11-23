<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Concerns\HasTemplateTabs;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    use HasTemplateTabs;

    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->templateCreateAction(),
        ];
    }
}
