<?php

namespace App\Filament\Resources\ServiceAreas\Tables;

use App\Models\ServiceArea;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ServiceAreasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (ServiceArea $r) => Media::url($r->image))->square()->imageSize(44),
                TextColumn::make('name')->searchable()->sortable()->description(fn (ServiceArea $r) => $r->state),
                TextColumn::make('localities')->label('Localities')->state(fn (ServiceArea $r) => count($r->localities ?? []))->alignCenter(),
                TextColumn::make('response_time')->label('Response'),
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
