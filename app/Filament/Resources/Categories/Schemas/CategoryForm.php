<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Category')->tabs([
                    Tab::make('Details')
                        ->icon('heroicon-o-squares-2x2')
                        ->schema([
                            Grid::make(2)->schema([
                                ...Fields::nameAndSlug(),
                                Select::make('parent_id')
                                    ->label('Parent category')
                                    ->relationship('parent', 'name', fn ($query, $record) => $record ? $query->whereKeyNot($record->getKey()) : $query)
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('None (top level)'),
                                TextInput::make('tagline')->maxLength(120)->helperText('Short line shown on category tiles.'),
                            ]),
                            Textarea::make('description')->rows(3),
                            Fields::image('image', 'Category image (portrait, 4:5)', 'categories'),
                            Grid::make(3)->schema([
                                Toggle::make('is_active')->label('Visible on storefront')->default(true),
                                Toggle::make('show_in_menu')->label('Show in navigation & homepage grid')->default(true),
                                TextInput::make('sort_order')->numeric()->default(0),
                                Fields::templateVisibility()->columnSpan(3),
                            ]),
                        ]),
                    Fields::seoTab(),
                ]),
            ]);
    }
}
