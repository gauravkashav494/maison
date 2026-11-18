<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Concerns\HasTemplateTabs;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    use HasTemplateTabs;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->templateCreateAction(),
        ];
    }
}
