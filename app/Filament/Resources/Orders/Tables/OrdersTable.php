<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Clients\ClientContext;
use App\Filament\Resources\Orders\OrderStatusActions;
use App\Filament\Support\CategoryFilter;
use App\Filament\Support\DateRangeFilter;
use App\Filament\Support\RowActions;
use App\Models\Customer;
use App\Models\Order;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

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
                TextColumn::make('number')->label('Order')->searchable()->weight('bold')->copyable()
                    // "New" until staff act on the order (Order::isNew), so nothing waiting is ever hidden.
                    ->formatStateUsing(fn (string $state, Order $record) => $record->isNew()
                        ? new HtmlString(e($state).' '.Blade::render('<x-filament::badge color="success" size="sm" class="ms-1 inline-flex align-middle">New</x-filament::badge>'))
                        : $state),
                TextColumn::make('created_at')->label('Placed')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('shipping_name')->label('Customer')->searchable()->description(fn (Order $r) => $r->email),
                // Orders a sales person placed (for themselves or a customer): no delivery.
                TextColumn::make('salesperson.name')->label('Placed by')->badge()->color('info')->placeholder('—')->toggleable(),
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
                // When it was invoiced, went out, arrived or was cancelled; off until switched on in the Columns menu.
                TextColumn::make('invoiced_at')->label('Invoiced')->dateTime('d M Y, H:i')->sortable()->placeholder('—')
                    ->description(fn (Order $r) => $r->invoice_number)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dispatched_at')->label('Dispatched')->dateTime('d M Y, H:i')->sortable()->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('delivered_at')->label('Delivered')->dateTime('d M Y, H:i')->sortable()->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cancelled_at')->label('Cancelled')->dateTime('d M Y, H:i')->sortable()->placeholder('—')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Several statuses at once, so "confirmed + processing" (still to fulfil) is one view.
                SelectFilter::make('status')->options(Order::statusOptions())->multiple()->native(false),
                SelectFilter::make('payment_method')->options(Order::PAYMENT_METHODS)->native(false),
                // Orders with at least one product in this category or any below it.
                CategoryFilter::through('items.product'),
                TernaryFilter::make('sales_order')->label('Sales orders')
                    ->trueLabel('Sales orders only')->falseLabel('Customer orders only')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('salesperson_id'),
                        false: fn (Builder $q) => $q->whereNull('salesperson_id'),
                        blank: fn (Builder $q) => $q,
                    ),
                // Each sales person's orders, or every sales order at once.
                SelectFilter::make('salesperson_id')->label('Placed by sales person')->native(false)->searchable()
                    ->options(fn () => Customer::where('client_id', app(ClientContext::class)->id() ?? 0)
                        ->where('is_salesperson', true)->orderBy('name')->pluck('name', 'id')->all()),

                // When it was placed: one picker with ready ranges (Today, Last 7 days, This month…);
                // pick the same day twice for one day.
                DateRangeFilter::range('placed_between', 'Placed between',
                    scope: fn (Builder $query, string $from, string $until) => $query->placedBetween($from, $until)),
            ])
            // Filters apply with the "Apply" button and lay out by how many there are, like every
            // table (AppServiceProvider::registerTableFilterLayout()).
            ->recordActions([RowActions::group([
                ...OrderStatusActions::all(),
                ViewAction::make(),
                OrderStatusActions::viewInvoice(),
                OrderStatusActions::invoice(),
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
