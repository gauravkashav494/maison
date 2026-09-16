<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Models\Store;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (Store $r) => Media::url($r->image))->square()->imageSize(44),
                TextColumn::make('name')->searchable()->weight('bold')->description(fn (Store $r) => $r->address),
                TextColumn::make('city')->sortable(),
                TextColumn::make('phone')->color('gray'),
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
