<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Order list and the manual fulfilment actions: Dispatch, Delivered and Cancel.
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
                TextColumn::make('total')->formatStateUsing(fn (int $state) => money($state))->sortable()->alignRight(),
                TextColumn::make('payment_method')->formatStateUsing(fn ($state) => Order::PAYMENT_METHODS[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('status')
                    ->formatStateUsing(fn (Order $r) => $r->statusLabel())
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'info', 'processing' => 'warning', 'shipped', 'out_for_delivery' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger', default => 'gray',
                    })
                    ->description(fn (Order $r) => match (true) {
                        $r->cancelled_at !== null => 'Cancelled '.$r->cancelled_at->format('d M Y'),
                        $r->delivered_at !== null => 'Delivered '.$r->delivered_at->format('d M Y'),
                        $r->dispatched_at !== null => 'Dispatched '.$r->dispatched_at->format('d M Y'),
                        default => null,
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(Order::statusOptions())->native(false),
                SelectFilter::make('payment_method')->options(Order::PAYMENT_METHODS)->native(false),

                // One exact day, for "what came in on the 14th".
                Filter::make('placed_on')
                    ->schema([DatePicker::make('date')->label('Placed on')->native(false)])
                    // The date logic itself lives on the model, so it is one definition and testable.
                    ->query(fn (Builder $query, array $data) => $query->placedOn($data['date'] ?? null))
                    ->indicateUsing(fn (array $data) => filled($data['date'] ?? null)
                        ? 'Placed on '.\Illuminate\Support\Carbon::parse($data['date'])->format('d M Y')
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
                        filled($data['from'] ?? null) and $parts[] = 'from '.\Illuminate\Support\Carbon::parse($data['from'])->format('d M Y');
                        filled($data['until'] ?? null) and $parts[] = 'until '.\Illuminate\Support\Carbon::parse($data['until'])->format('d M Y');

                        return $parts ? 'Placed '.implode(' ', $parts) : null;
                    }),
            ])
            ->filtersFormColumns(2)
            // Every applied filter shows as a removable chip, and "Reset filters" clears them all.
            ->deferFilters(false)
            ->recordActions([
                Action::make('dispatch')
                    ->label('Dispatch')
                    ->icon('heroicon-o-truck')
                    ->color('primary')
                    ->authorize(fn (Order $record) => self::mayFulfil($record))
                    ->visible(fn (Order $record) => $record->canTransitionTo(Order::STATUS_DISPATCHED))
                    ->schema([
                        TextInput::make('carrier')->label('Carrier (optional)')->default(fn (Order $record) => $record->carrier),
                        TextInput::make('tracking_number')->label('Tracking number (optional)')->default(fn (Order $record) => $record->tracking_number),
                        TextInput::make('note')->label('Note')->placeholder('Shown on the customer timeline'),
                    ])
                    ->action(fn (Order $record, array $data) => self::move($record, Order::STATUS_DISPATCHED, $data['note'] ?? null, [
                        'carrier' => $data['carrier'] ?? null,
                        'tracking_number' => $data['tracking_number'] ?? null,
                    ])),

                Action::make('deliver')
                    ->label('Delivered')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->authorize(fn (Order $record) => self::mayFulfil($record))
                    ->visible(fn (Order $record) => $record->canTransitionTo(Order::STATUS_DELIVERED))
                    ->requiresConfirmation()
                    ->modalDescription('Records this order as delivered. This closes the order.')
                    ->schema([TextInput::make('note')->label('Note')->placeholder('Who received it, for example')])
                    ->action(fn (Order $record, array $data) => self::move($record, Order::STATUS_DELIVERED, $data['note'] ?? null)),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->authorize(fn (Order $record) => self::mayFulfil($record))
                    ->visible(fn (Order $record) => $record->canTransitionTo(Order::STATUS_CANCELLED))
                    ->schema([
                        Textarea::make('reason')->label('Reason')->required()->rows(2)
                            ->helperText('Recorded with the order and shown on the timeline.'),
                    ])
                    ->action(fn (Order $record, array $data) => self::move($record, Order::STATUS_CANCELLED, $data['reason'])),

                // The full pipeline, for the steps between dispatch and delivery.
                Action::make('advance')
                    ->label('Update status')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->authorize(fn (Order $record) => self::mayFulfil($record))
                    ->visible(fn (Order $record) => $record->allowedTransitions() !== [])
                    ->schema([
                        Select::make('status')
                            ->options(fn (Order $record) => array_intersect_key(Order::statusOptions(), array_flip($record->allowedTransitions())))
                            ->required()
                            ->native(false)
                            ->default(fn (Order $record) => $record->allowedTransitions()[0] ?? null),
                        TextInput::make('tracking_number')->default(fn (Order $record) => $record->tracking_number),
                        TextInput::make('note')->placeholder('Optional note shown on the timeline'),
                    ])
                    ->action(fn (Order $record, array $data) => self::move($record, $data['status'], $data['note'] ?? null, [
                        'tracking_number' => $data['tracking_number'] ?? null,
                    ])),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkMove('dispatchSelected', 'Mark as dispatched', Order::STATUS_DISPATCHED, 'heroicon-o-truck', 'primary'),
                    self::bulkMove('deliverSelected', 'Mark as delivered', Order::STATUS_DELIVERED, 'heroicon-o-check-badge', 'success'),
                ]),
            ]);
    }

    /** Only an account the policy lets edit this order may change its status. */
    private static function mayFulfil(Order $order): bool
    {
        return (bool) filament()->auth()->user()?->can('update', $order);
    }

    /**
     * Applies one status change. The transition is checked again here, so an action triggered
     * from a page that was open while the order moved on is refused rather than applied.
     */
    private static function move(Order $order, string $status, ?string $note = null, array $attributes = []): void
    {
        $attributes = array_filter($attributes, fn ($value) => filled($value));
        if ($attributes) {
            $order->update($attributes);
        }

        $label = Order::statusOptions()[$status] ?? $status;

        if (! $order->transitionTo($status, $note, filament()->auth()->user())) {
            Notification::make()
                ->danger()
                ->title('That change is not allowed')
                ->body($order->number.' is '.$order->statusLabel().', so it cannot be marked as '.$label.'.')
                ->send();

            return;
        }

        Notification::make()->success()->title($order->number.' is now '.$label)->send();
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
                    if (! self::mayFulfil($order)) {
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
