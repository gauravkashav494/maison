<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\OrderStatusActions;
use App\Filament\Support\RowActions;
use App\Models\Order;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Order list. Each row offers the fulfilment actions (OrderStatusActions), the same ones as the order page.
 *
 * Fulfilment is done by the client, not by a shipping integration, so each action simply
 * records the new status — but only when the order is allowed to make that move
 * (Order::allowedTransitions), and always with who did it and when. The buttons hide
 * themselves for a move that is not allowed, and the action checks again before writing, so
 * a stale page cannot force an invalid change.
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('Order')->searchable()->weight('bold')->copyable(),
                TextColumn::make('created_at')->label('Placed')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('shipping_name')->label('Customer')->searchable()->description(fn (Order $r) => $r->email),
                TextColumn::make('items_count')->counts('items')->label('Items')->alignRight(),
                TextColumn::make('total')->formatStateUsing(fn (float $state) => money($state))->sortable()->alignRight(),
                TextColumn::make('payment_method')->formatStateUsing(fn ($state) => Order::PAYMENT_METHODS[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('status')
                    ->formatStateUsing(fn (Order $r) => $r->statusLabel())
                    ->badge()
                    ->color(fn (Order $r) => $r->statusColor())
                    ->description(fn (Order $r) => match (true) {
                        $r->cancelled_at !== null => 'Cancelled '.$r->cancelled_at->format('d M Y'),
                        $r->delivered_at !== null => 'Delivered '.$r->delivered_at->format('d M Y'),
                        $r->dispatched_at !== null => 'Dispatched '.$r->dispatched_at->format('d M Y'),
                        default => null,
                    }),
            ])
            ->filters([
                // Several statuses at once, so "confirmed + processing" (still to fulfil) is one view.
                SelectFilter::make('status')->options(Order::statusOptions())->multiple()->native(false),
                SelectFilter::make('payment_method')->options(Order::PAYMENT_METHODS)->native(false),

                // One exact day, for "what came in on the 14th".
                Filter::make('placed_on')
                    ->schema([DatePicker::make('date')->label('Placed on')->native(false)])
                    // The date logic itself lives on the model, so it is one definition and testable.
                    ->query(fn (Builder $query, array $data) => $query->placedOn($data['date'] ?? null))
                    ->indicateUsing(fn (array $data) => filled($data['date'] ?? null)
                        ? 'Placed on '.Carbon::parse($data['date'])->format('d M Y')
                        : null),

                // Or a range; either end may be left open.
                Filter::make('placed_between')
                    ->schema([
                        DatePicker::make('from')->label('Placed from')->native(false),
                        DatePicker::make('until')->label('Placed until')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->placedBetween($data['from'] ?? null, $data['until'] ?? null))
                    ->indicateUsing(function (array $data) {
                        $parts = [];
                        filled($data['from'] ?? null) and $parts[] = 'from '.Carbon::parse($data['from'])->format('d M Y');
                        filled($data['until'] ?? null) and $parts[] = 'until '.Carbon::parse($data['until'])->format('d M Y');

                        return $parts ? 'Placed '.implode(' ', $parts) : null;
                    }),
            ])
            // Filters apply with the "Apply" button and lay out by how many there are, like every
            // table (AppServiceProvider::registerTableFilterLayout()).
            ->recordActions([RowActions::group([
                ...OrderStatusActions::all(),
                ViewAction::make(),
                EditAction::make(),
            ])])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkMove('dispatchSelected', 'Mark as dispatched', Order::STATUS_DISPATCHED, 'heroicon-o-truck', 'primary'),
                    self::bulkMove('deliverSelected', 'Mark as delivered', Order::STATUS_DELIVERED, 'heroicon-o-check-badge', 'success'),
                ]),
            ]);
    }

    /** Bulk version: each order is checked on its own and the result is reported together. */
    private static function bulkMove(string $name, string $label, string $status, string $icon, string $color): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($status, $label) {
                $done = 0;
                $skipped = [];
                foreach ($records as $order) {
                    if (! OrderStatusActions::mayFulfil($order)) {
                        $skipped[] = $order->number;

                        continue;
                    }
                    $order->transitionTo($status, null, filament()->auth()->user())
                        ? $done++
                        : $skipped[] = $order->number;
                }

                Notification::make()
                    ->status($skipped ? 'warning' : 'success')
                    ->title($done.' '.str('order')->plural($done).' marked as '.$label)
                    ->body($skipped ? 'Left unchanged (not allowed from their current status): '.implode(', ', $skipped) : null)
                    ->send();
            });
    }
}
