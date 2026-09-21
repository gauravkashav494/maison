<?php

namespace App\Filament\Resources\Services\Tables;

use App\Models\Service;
use App\Support\Media;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (Service $r) => Media::url($r->image))->square()->imageSize(44),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Service $r) => str($r->excerpt)->limit(70)),
                IconColumn::make('is_emergency')->label('Emergency')->boolean()->trueIcon('heroicon-o-exclamation-triangle')->trueColor('danger')->falseIcon('')->alignCenter(),
                ToggleColumn::make('is_popular')->label('Popular'),
                ToggleColumn::make('is_active')->label('Visible'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
