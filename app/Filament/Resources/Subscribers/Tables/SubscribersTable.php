<?php

namespace App\Filament\Resources\Subscribers\Tables;

use App\Filament\Support\RowActions;
use App\Models\Subscriber;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('source')->badge()->color('gray'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Subscriber $r) => $r->isUnsubscribed() ? 'Unsubscribed' : 'Subscribed')
                    ->color(fn (string $state) => $state === 'Subscribed' ? 'success' : 'gray')
                    ->tooltip(fn (Subscriber $r) => $r->unsubscribed_at?->format('\L\e\f\t d M Y, H:i')),
                TextColumn::make('created_at')->label('Subscribed')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('status')->label('Status')
                    ->placeholder('All')->trueLabel('Subscribed')->falseLabel('Unsubscribed')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('unsubscribed_at'),
                        false: fn (Builder $q) => $q->whereNotNull('unsubscribed_at'),
                        blank: fn (Builder $q) => $q,
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([RowActions::group([
                EditAction::make(),
                // For someone who asks by reply or phone instead of using their link.
                Action::make('unsubscribe')->label('Mark unsubscribed')->icon('heroicon-o-no-symbol')->color('gray')
                    ->visible(fn (Subscriber $r) => ! $r->isUnsubscribed() && ! $r->trashed())
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalDescription('They stay in the list as Unsubscribed and are left out of exports of subscribed emails. Signing up again on the website re-subscribes them.')
                    ->action(fn (Subscriber $r) => $r->forceFill(['unsubscribed_at' => now()])->save()),
                ...RowActions::trash(),
            ])])
            ->toolbarActions([BulkActionGroup::make([...RowActions::trashBulk()])]);
    }
}
