<?php

namespace App\Filament\Resources\Collections\Schemas;

use App\Filament\Support\Fields;
use App\Models\Collection;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Details')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        // Name and season on one row; stacked on phones. The page address
                        // (/collections/{slug}) is made from the name on save (GeneratesSlug).
                        // The order is set by dragging rows in the collections list, not here.
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            Fields::nameWithAddress('/collections'),
                            TextInput::make('season')->maxLength(60)->placeholder('Autumn / Winter'),
                        ]),
                        Textarea::make('description')->rows(3)->maxLength(600)->helperText('Shown on collection cards and the collection hero.'),
                        RichEditor::make('body')->label('Story')->helperText('Optional long-form editorial shown on the collection page.'),
                        Grid::make(2)->schema([
                            Toggle::make('is_active')->label('Visible on storefront')->default(true),
                            Fields::templateVisibility()->columnSpan(2),
                        ]),
                    ]),
                // Imagery and Products can be folded away; the two images sit side by side (stacked on phones).
                Section::make('Imagery')
                    ->icon('heroicon-o-photo')
                    ->collapsible()
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Fields::image('image', 'Card image (portrait 4:5)', 'collections'),
                        Fields::image('hero_image', 'Campaign hero (landscape, optional)', 'collections'),
                    ]),
                Section::make('Products')
                    ->icon('heroicon-o-shopping-bag')
                    ->collapsible()
                    ->schema([
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Products shown in this collection. A product sits in one collection, so adding it here moves it out of its current one.')
                            // Saves the list (keeping each product's place) and takes the products out of
                            // any other collection, so every product stays in at most one.
                            ->saveRelationshipsUsing(function (Collection $record, $state): void {
                                $ids = array_map('intval', (array) $state);
                                $record->products()->sync($ids);
                                if ($ids) {
                                    $record->products()->newPivotStatement()
                                        ->whereIn('product_id', $ids)
                                        ->where('collection_id', '!=', $record->getKey())
                                        ->delete();
                                }
                            }),
                    ]),
            ]);
    }
}
