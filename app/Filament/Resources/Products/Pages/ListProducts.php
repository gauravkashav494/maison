<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Concerns\HasTemplateTabs;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    use HasTemplateTabs;

    protected static string $resource = ProductResource::class;

    public function getSubheading(): ?string
    {
        $quota = \App\Admin\StoreQuota::current();
        if (! $quota || $quota->productLimit() === null) {
            return null;
        }

        return $quota->atProductLimit()
            ? 'Product limit reached: '.$quota->productLabel().'. Delete a product, or ask the platform admin to raise the limit.'
            : 'Using '.$quota->productLabel().' products in this store.';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->templateCreateAction(),
        ];
    }
}
