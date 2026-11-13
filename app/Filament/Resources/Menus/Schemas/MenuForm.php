<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Filament\Resources\Menus\MenuResource;
use App\Models\Menu;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->where('client_id', app(\App\Clients\ClientContext::class)->id()))
                ->helperText('Where on your storefront this menu appears. Each location holds one menu.'),
        ]);
    }
}
