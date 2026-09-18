<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (Category $r) => Media::url($r->image))->square()->imageSize(44),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Category $r) => $r->tagline),
                TextColumn::make('parent.name')->label('Parent')->badge()->color('gray')->placeholder('Top level'),
                TextColumn::make('products_count')->counts('products')->label('Products')->alignRight(),
                ToggleColumn::make('is_active')->label('Visible'),
                ToggleColumn::make('show_in_menu')->label('Homepage'),
            ])
            ->filters([
                TernaryFilter::make('parent_id')->label('Level')->nullable()->trueLabel('Sub-categories')->falseLabel('Top level')->queries(
                    true: fn ($q) => $q->whereNotNull('parent_id'),
                    false: fn ($q) => $q->whereNull('parent_id'),
                ),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
