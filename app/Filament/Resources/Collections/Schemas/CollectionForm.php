<?php

namespace App\Filament\Resources\Collections\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Collection')->tabs([
                    Tab::make('Details')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Grid::make(2)->schema([
                                ...Fields::nameAndSlug(),
                                TextInput::make('season')->maxLength(60)->placeholder('Autumn / Winter'),
                                TextInput::make('sort_order')->numeric()->default(0),
                            ]),
                            Textarea::make('description')->rows(3)->maxLength(600)->helperText('Shown on collection cards and the collection hero.'),
                            RichEditor::make('body')->label('Story')->helperText('Optional long-form editorial shown on the collection page.'),
                            Grid::make(2)->schema([
                                Toggle::make('is_active')->label('Visible on storefront')->default(true),
                                Toggle::make('is_featured')->label('Featured')->helperText('Eligible for the homepage featured banner.'),
                            ]),
                        ]),
                    Tab::make('Imagery')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Fields::image('image', 'Card image (portrait 4:5)', 'collections'),
                            Fields::image('hero_image', 'Campaign hero (landscape, optional)', 'collections'),
                        ]),
                    Tab::make('Products')
                        ->icon('heroicon-o-shopping-bag')
                        ->schema([
                            Select::make('products')
                                ->relationship('products', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->helperText('Products shown in this collection.'),
                        ]),
                    Fields::seoTab(),
                ]),
            ]);
    }
}
