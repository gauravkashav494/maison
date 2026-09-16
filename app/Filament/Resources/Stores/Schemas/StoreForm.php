<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Boutique')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                Toggle::make('is_active')->label('Visible')->default(true)->inline(false),
                TextInput::make('address')->required()->columnSpanFull(),
                TextInput::make('city')->required(),
                TextInput::make('state'),
                TextInput::make('postal_code'),
                TextInput::make('country')->default('India'),
                TextInput::make('phone'),
                TextInput::make('email')->email(),
                Textarea::make('hours')->rows(3)->helperText('One line per day range, e.g. "Mon–Sat 10:30–20:00".')->columnSpanFull(),
            ]),
            Section::make('Map & imagery')->columns(3)->schema([
                TextInput::make('lat')->label('Latitude')->numeric(),
                TextInput::make('lng')->label('Longitude')->numeric(),
                TextInput::make('maps_url')->label('Google Maps link')->url(),
                Fields::image('image', 'Boutique photo (landscape)', 'stores')->columnSpanFull(),
            ]),
            Grid::make(3)->schema([TextInput::make('sort_order')->numeric()->default(0)]),
        ]);
    }
}
