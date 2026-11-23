<?php

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Concerns\HasTemplateTabs;
use App\Filament\Resources\Brands\BrandResource;
use Filament\Resources\Pages\ListRecords;

class ListBrands extends ListRecords
{
    use HasTemplateTabs;

    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->templateCreateAction(),
        ];
    }
}
