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
        $quota = \App\Clients\ClientQuota::current();
        if (! $quota || ($quota->productLimit() === null && $quota->imageLimit() === null)) {
            return null;
        }

        $parts = [];
        if ($quota->productLimit() !== null) {
            $parts[] = $quota->atProductLimit()
                ? 'Product limit reached: '.$quota->productLabel().'. Delete a product, or ask the platform admin to raise the limit.'
                : 'Using '.$quota->productLabel().' products.';
        }
        if ($quota->imageLimit() !== null) {
            $parts[] = $quota->atImageLimit()
                ? 'Image limit reached: '.$quota->imageLabel().'. Remove an image, or ask the platform admin to raise the limit.'
                : 'Using '.$quota->imageLabel().' product images.';
        }

        return implode(' ', $parts);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->templateCreateAction(),
        ];
    }
}
