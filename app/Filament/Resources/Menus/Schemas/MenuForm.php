<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Templates\TemplateManager;
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
                ->options(fn () => app(TemplateManager::class)->allMenuLocations())
                ->required()
                ->unique(ignoreRecord: true)
                ->helperText('Where on the storefront this menu is rendered. Each template has its own locations.'),
        ]);
    }
}
