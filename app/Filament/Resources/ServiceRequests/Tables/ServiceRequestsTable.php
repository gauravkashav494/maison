<?php

namespace App\Filament\Resources\ServiceRequests\Tables;

use App\Models\ServiceRequest;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ServiceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')->searchable()->weight('bold')->description(fn (ServiceRequest $r) => $r->created_at->format('d M Y, g:i a')),
                TextColumn::make('type')->badge()->formatStateUsing(fn (string $state) => ServiceRequest::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'emergency' => 'danger', 'quote' => 'warning', default => 'info' }),
                TextColumn::make('service_name')->label('Service')->searchable()->description(fn (ServiceRequest $r) => str($r->problem)->limit(50)),
                TextColumn::make('name')->label('Customer')->searchable()->description(fn (ServiceRequest $r) => $r->phone),
                TextColumn::make('area')->label('Area')->searchable()->toggleable(),
                TextColumn::make('preferred_date')->label('When')->date('d M')->description(fn (ServiceRequest $r) => $r->slot_label),
                SelectColumn::make('status')->options(ServiceRequest::STATUSES)->selectablePlaceholder(false),
            ])
            ->filters([
                SelectFilter::make('type')->options(ServiceRequest::TYPES),
                SelectFilter::make('status')->options(ServiceRequest::STATUSES),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('contacted')->label('Mark contacted')->icon('heroicon-o-phone')->action(fn (Collection $records) => $records->each->update(['status' => 'contacted']))->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ])]);
    }
}
