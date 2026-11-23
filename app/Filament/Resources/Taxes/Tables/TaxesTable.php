<?php

namespace App\Filament\Resources\Taxes\Tables;

use App\Filament\Support\RowActions;
use App\Models\Tax;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TaxesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('rate')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('rate')->label('Rate')->sortable()->alignRight()
                    ->formatStateUsing(fn ($state) => Tax::rateLabel((float) $state)),
                TextColumn::make('products_count')->counts('products')->label('Products')->sortable()->alignRight(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([RowActions::group([EditAction::make(), ...RowActions::trash()])])
            ->toolbarActions([BulkActionGroup::make(RowActions::trashBulk())])
            ->emptyStateHeading('No taxes yet')
            ->emptyStateDescription('Add the taxes you charge (name and rate), then pick one on each product.');
    }
}
