<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Filament\Resources\Reviews\ReviewResource;
use App\Models\Review;
use App\Filament\Support\RowActions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** A product's reviews on its detail page. Read-only here; ⋮ → Edit opens the review to moderate it. */
class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Reviews';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class) && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('rating')->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))->color('warning')->sortable(),
                TextColumn::make('title')->weight('medium')->description(fn (Review $r) => str($r->body)->limit(90))->wrap(),
                TextColumn::make('name')->label('By')->description(fn (Review $r) => $r->email),
                IconColumn::make('is_verified_purchase')->label('Verified')->boolean(),
                IconColumn::make('is_approved')->label('Approved')->boolean(),
                TextColumn::make('held_reason')->label('Waiting because')->badge()->color('warning')
                    ->state(fn (Review $r) => $r->is_approved ? null : $r->held_reason)->placeholder('—'),
                TextColumn::make('created_at')->label('Posted')->date('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('rating')->options(Review::RATING_OPTIONS)->multiple()->native(false),
                TernaryFilter::make('is_approved')->label('Approved'),
            ])
            ->recordActions([RowActions::group([
                Action::make('edit')->label('Edit')->icon('heroicon-m-pencil-square')
                    ->url(fn (Review $r) => ReviewResource::getUrl('edit', ['record' => $r]))
                    ->visible(fn (Review $r) => ReviewResource::canEdit($r)),
            ])])
            ->emptyStateHeading('No reviews yet');
    }
}
