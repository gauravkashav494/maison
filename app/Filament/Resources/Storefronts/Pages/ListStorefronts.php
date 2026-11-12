<?php

namespace App\Filament\Resources\Storefronts\Pages;

use App\Filament\Resources\Storefronts\StorefrontResource;
use App\Models\Storefront;
use Filament\Resources\Pages\ListRecords;

class ListStorefronts extends ListRecords
{
    protected static string $resource = StorefrontResource::class;

    public function mount(): void
    {
        Storefront::syncWithTemplates(); // a newly registered template gets its store row automatically
        parent::mount();
    }

    public function getSubheading(): ?string
    {
        return 'One store per template, created automatically when a template is added.';
    }
}
