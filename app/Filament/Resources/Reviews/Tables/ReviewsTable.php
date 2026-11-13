<?php

namespace App\Filament\Resources\Reviews\Tables;

use App\Clients\ClientAccess;
use App\Filament\Support\RowActions;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.name')->searchable()->sortable()->limit(30),
                TextColumn::make('rating')->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))->color('warning')->sortable(),
                TextColumn::make('name')->searchable()->description(fn (Review $r) => $r->title),
                TextColumn::make('body')->limit(60)->color('gray')->wrap(),
                IconColumn::make('is_verified_purchase')->label('Verified')->boolean()
                    ->tooltip(fn (Review $r) => $r->is_verified_purchase ? 'Signed in, and has a delivered order with this product' : null),
                ToggleColumn::make('is_approved')->label('Approved'),
                // Why a review that is still waiting was held back by the automatic check.
                TextColumn::make('held_reason')->label('Waiting because')->badge()->color('warning')
                    ->state(fn (Review $r) => $r->is_approved ? null : $r->held_reason)
                    ->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                // Several at once, e.g. 1 and 2 stars to find unhappy customers.
                SelectFilter::make('rating')->options(Review::RATING_OPTIONS)->multiple()->native(false),
                TernaryFilter::make('is_approved')->label('Approved'),
                TernaryFilter::make('is_verified_purchase')->label('Verified purchase'),
                TrashedFilter::make(),
            ])
            ->recordActions([RowActions::group([
                Action::make('approve')->icon('heroicon-o-check')->color('success')
                    ->authorize(fn () => ClientAccess::current('reviews', 'update'))
                    ->visible(fn (Review $r) => ! $r->is_approved)
                    ->action(fn (Review $r) => $r->update(['is_approved' => true])),
                EditAction::make(),
                ...RowActions::trash(),
            ])])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')->label('Approve selected')->icon('heroicon-o-check')
                        ->authorize(fn () => ClientAccess::current('reviews', 'update'))
                        ->action(fn (Collection $records) => $records->each->update(['is_approved' => true]))->deselectRecordsAfterCompletion(),
                    ...RowActions::trashBulk(),
                ]),
            ]);
    }
}
