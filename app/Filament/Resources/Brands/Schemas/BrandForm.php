<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\Textarea;
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
                    ...self::fields(),
                    Textarea::make('description')->rows(3)->maxLength(600),
                    Fields::image('logo', 'Logo', 'brands'),
                    // The order is set by dragging rows in the brands list (then A to Z).
                    Grid::make(2)->schema([
                        Fields::templateVisibility(),
                    ]),
                ]),
            ]);
    }

    /**
     * The name, also used to add a brand straight from the product form. Its page address
     * (/brands/{slug}) is made from the name on save (GeneratesSlug).
     */
    public static function fields(): array
    {
        return [Fields::nameWithAddress('/brands')];
    }
}
