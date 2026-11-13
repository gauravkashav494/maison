<?php

namespace App\Filament\Resources\Collections\Tables;

use App\Filament\Support\RowActions;
use App\Models\Collection;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CollectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (Collection $r) => Media::url($r->image))->square()->imageSize(44),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Collection $r) => $r->season),
                TextColumn::make('products_count')->counts('products')->label('Products')->alignRight(),
                ToggleColumn::make('is_featured')->label('Featured'),
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([RowActions::group([ViewAction::make(), EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make(RowActions::trashBulk())]);
    }
}
