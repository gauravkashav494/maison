<?php

namespace App\Filament\Resources\Taxes\Schemas;

use App\Filament\Support\Fields;
use App\Models\Tax;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaxForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Tax')
                    ->description('Products pick a tax; its rate works out their price including and excluding tax. Changing the rate here updates every product with this tax.')
                    ->schema([
                        Grid::make(2)->schema(self::fields()),
                        Fields::templateVisibility(),
                    ]),
            ]);
    }

    /** Name and rate, also used to add a tax straight from the product form. */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->required()->maxLength(80)
                ->placeholder('e.g. GST 18%'),
            Select::make('rate')
                ->label('GST rate')
                // The GST slabs; a rate a tax already has is kept in the list.
                ->options(fn (?Tax $record) => Tax::rateOptions($record?->rate))
                // Saved as a number (18.00); the options are keyed "18" and compared strictly.
                ->formatStateUsing(fn ($state) => is_numeric($state) ? (string) (float) $state : $state)
                ->dehydrateStateUsing(fn ($state) => (float) $state)
                ->default('18')
                ->required()->selectablePlaceholder(false)->native(false),
        ];
    }
}
