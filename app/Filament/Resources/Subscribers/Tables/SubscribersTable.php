<?php

namespace App\Filament\Resources\Subscribers\Tables;

use App\Filament\Support\RowActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('source')->badge()->color('gray'),
                TextColumn::make('created_at')->label('Subscribed')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([RowActions::group([EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make([...RowActions::trashBulk()])]);
    }
}
