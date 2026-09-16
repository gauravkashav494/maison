<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Support\Fields;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Product')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Details')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Grid::make(2)->schema([
                                    ...Fields::nameAndSlug(),
                                    Select::make('category_id')
                                        ->label('Category')
                                        ->relationship('category', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required(),
                                    TextInput::make('sku')->label('SKU')->maxLength(64)->unique(ignoreRecord: true),
                                    TextInput::make('brand')->maxLength(80)->datalist(fn () => \App\Models\Product::query()->whereNotNull('brand')->distinct()->pluck('brand')->all())->helperText('Used by the shop Brand filter.'),
                                    TextInput::make('material')->label('Primary material')->maxLength(80)->datalist(fn () => \App\Models\Product::query()->whereNotNull('material')->distinct()->pluck('material')->all())->helperText('Used by the shop Material filter.'),
                                ]),
                                Textarea::make('description')
                                    ->label('Short description')
                                    ->rows(3)
                                    ->maxLength(600)
                                    ->helperText('Shown on product cards, quick view and search results.'),
                                RichEditor::make('details')
                                    ->label('Full description')
                                    ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'h3', 'link', 'undo', 'redo']),
                                Grid::make(2)->schema([
                                    Textarea::make('materials')->rows(3),
                                    Textarea::make('care')->label('Care instructions')->rows(3),
                                ]),
                                Select::make('collections')
                                    ->relationship('collections', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                            ]),

                        Tab::make('Media & Variants')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Fields::image('images', 'Product images', 'products', multiple: true)
                                    ->helperText('Drag to reorder. The first image is the main image; the second is shown on hover.')
                                    ->required(),
                                TextInput::make('video_url')->label('Product video (optional)')->url()->placeholder('https://…/video.mp4 or a YouTube link')->helperText('Shown as the last slide of the gallery.'),
                                Section::make('Variants')->schema([
                                    Repeater::make('colors')
                                        ->label('Colours')
                                        ->schema([
                                            TextInput::make('name')->required()->maxLength(40),
                                            ColorPicker::make('hex')->label('Swatch')->required(),
                                        ])
                                        ->columns(2)
                                        ->reorderable()
                                        ->defaultItems(0)
                                        ->addActionLabel('Add colour')
                                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                                    TagsInput::make('sizes')
                                        ->placeholder('Add a size and press Enter')
                                        ->helperText('e.g. XS, S, M, L — or 50ml, 100ml — or "One size".')
                                        ->reorderable(),
                                ]),
                            ]),

                        Tab::make('Pricing & Inventory')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('price')->numeric()->prefix('₹')->required()->minValue(0),
                                    TextInput::make('compare_at_price')
                                        ->label('Compare-at price')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->minValue(0)
                                        ->helperText('Set higher than the price to show a sale badge and strikethrough.'),
                                    TextInput::make('stock')->numeric()->minValue(0)->default(100)->required(),
                                ]),
                                Section::make('Merchandising')->columns(3)->schema([
                                    Toggle::make('is_active')->label('Visible on storefront')->default(true),
                                    Toggle::make('is_new')->label('New arrival'),
                                    Toggle::make('is_best_seller')->label('Best seller'),
                                    TextInput::make('rating')->numeric()->minValue(0)->maxValue(5)->step(0.1)->default(0),
                                    TextInput::make('review_count')->numeric()->minValue(0)->default(0),
                                    TextInput::make('sort_order')->numeric()->default(0)->helperText('Lower numbers appear first.'),
                                ]),
                            ]),

                        Fields::seoTab(),
                    ]),
            ]);
    }
}
