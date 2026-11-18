<?php

namespace App\Filament\Resources\Taxes\Pages;

use App\Filament\Concerns\HasTemplateTabs;
use App\Filament\Resources\Taxes\TaxResource;
use Filament\Resources\Pages\ListRecords;

class ListTaxes extends ListRecords
{
    use HasTemplateTabs;

    protected static string $resource = TaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->templateCreateAction(),
        ];
    }
}
