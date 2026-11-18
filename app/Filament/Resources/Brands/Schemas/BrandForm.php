<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Brand')->schema([
                    Grid::make(2)->schema(self::fields()),
                    Textarea::make('description')->rows(3)->maxLength(600),
                    Fields::image('logo', 'Logo', 'brands'),
                    Grid::make(2)->schema([
                        TextInput::make('sort_order')->numeric()->default(0)->helperText('Lower numbers come first in lists.'),
                        Fields::templateVisibility(),
                    ]),
                ]),
            ]);
    }

    /** Name and slug, also used to add a brand straight from the product form. */
    public static function fields(): array
    {
        return Fields::nameAndSlug();
    }
}
