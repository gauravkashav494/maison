<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Filament\Resources\Menus\MenuResource;
use App\Filament\Concerns\HasTemplateTabs;
use Filament\Resources\Pages\ListRecords;

class ListMenus extends ListRecords
{
    use HasTemplateTabs;

    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Only while a location is free: a new menu needs a location of its own.
            $this->templateCreateAction()->visible(fn () => MenuResource::availableLocations() !== []),
        ];
    }

    public function getSubheading(): ?string
    {
        return MenuResource::availableLocations() === []
            ? 'Every menu location on your storefront already has a menu. To change the links, use ⋮ → Manage items. A deleted menu keeps its location until you restore it or delete it permanently (filter: Deleted items).'
            : null;
    }
}
