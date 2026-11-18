<?php

namespace App\Filament\Resources\Brands\Tables;

use App\Filament\Support\RowActions;
use App\Models\Brand;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('logo')->label('')->state(fn (Brand $r) => Media::url($r->logo))->square()->imageSize(44),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Brand $r) => $r->slug),
                TextColumn::make('products_count')->counts('products')->label('Products')->sortable()->alignRight(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([RowActions::group([ViewAction::make(), EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make(RowActions::trashBulk())])
            ->emptyStateHeading('No brands yet')
            ->emptyStateDescription('Add the brands you sell, then pick one on each product.');
    }
}
