<?php

namespace App\Filament\Resources\Storefronts\Pages;

use App\Filament\Resources\Storefronts\StorefrontResource;
use App\Models\Storefront;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStorefronts extends ListRecords
{
    protected static string $resource = StorefrontResource::class;

    public function mount(): void
    {
        Storefront::syncWithTemplates(); // a newly registered template gets its store row automatically
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
