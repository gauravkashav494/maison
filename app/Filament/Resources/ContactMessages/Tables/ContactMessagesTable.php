<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use App\Models\ContactMessage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('is_read')->label('')->boolean()->trueIcon('heroicon-o-envelope-open')->falseIcon('heroicon-s-envelope')->trueColor('gray')->falseColor('primary'),
                TextColumn::make('name')->searchable()->weight(fn (ContactMessage $r) => $r->is_read ? null : 'bold')->description(fn (ContactMessage $r) => $r->email),
                TextColumn::make('subject')->searchable()->badge()->color('gray')->placeholder('—'),
                TextColumn::make('message')->limit(70)->wrap()->color('gray'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([TernaryFilter::make('is_read')->label('Read')])
            ->recordActions([EditAction::make()->label('Open')->after(fn (ContactMessage $record) => $record->update(['is_read' => true]))])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
