<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->state(fn (Product $record) => Media::url($record->images[0] ?? null))
                    ->square()
                    ->imageSize(48),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $record) => $record->sku),
                TextColumn::make('category.name')->sortable()->badge()->color('gray'),
                TextColumn::make('price')
                    ->formatStateUsing(fn (int $state) => money($state))
                    ->sortable()
                    ->description(fn (Product $record) => $record->compare_at_price ? 'was '.money($record->compare_at_price) : null),
                TextColumn::make('stock')->sortable()->alignRight(),
                IconColumn::make('is_new')->label('New')->boolean()->toggleable(),
                IconColumn::make('is_best_seller')->label('Best seller')->boolean()->toggleable(),
                ToggleColumn::make('is_active')->label('Visible'),
                TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \App\Filament\Support\Fields::templateFilter(),
                SelectFilter::make('category')->relationship('category', 'name')->preload(),
                TernaryFilter::make('is_active')->label('Visible'),
                TernaryFilter::make('is_new')->label('New arrival'),
                TernaryFilter::make('is_best_seller')->label('Best seller'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
