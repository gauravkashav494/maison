<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Clients\ClientContext;
use App\Filament\Resources\Menus\MenuResource;
use App\Models\Menu;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(80),
            Select::make('location')
                // Only this store's template's locations that have no menu yet (plus this menu's own when editing).
                ->options(fn (?Menu $record) => MenuResource::availableLocations($record))
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(ClientContext::class)->id()))
                ->live()
                // Says where the picked location is on the store ("In the footer of every page").
                ->helperText(fn (?string $state) => ($state && ($where = MenuResource::placement($state)))
                    ? $where.'. Each location holds one menu.'
                    : 'Where on your storefront this menu appears. Each location holds one menu.'),
            // While adding a menu, the store section for the location picked above. Editing a menu
            // shows it after the menu items instead (EditMenu::content()).
            View::make('filament.menus.store-preview')
                ->columnSpanFull()
                // One visible() rule: a second one would replace it. Editing shows it after the items.
                ->visible(fn (Get $get, string $operation) => $operation === 'create' && MenuResource::previewUrl($get('location')) !== null)
                ->viewData(fn (Get $get) => [
                    'url' => MenuResource::previewUrl($get('location')),
                    'pageUrl' => MenuResource::previewUrl($get('location'), fullPage: true),
                    'placement' => MenuResource::placement($get('location')),
                ]),
        ]);
    }
}
