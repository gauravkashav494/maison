<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Filament\Support\RowActions;
use App\Models\Store;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (Store $r) => Media::url($r->image))->square()->imageSize(44),
                TextColumn::make('name')->searchable()->weight('bold')->description(fn (Store $r) => $r->address),
                TextColumn::make('city')->sortable(),
                TextColumn::make('phone')->color('gray'),
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([RowActions::group([EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make([...RowActions::trashBulk()])]);
    }
}
