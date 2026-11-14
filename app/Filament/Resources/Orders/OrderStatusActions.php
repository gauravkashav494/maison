<?php

namespace App\Filament\Resources\Orders;

use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * The manual fulfilment actions — Dispatch, Delivered, Cancel and Update status — shared by
 * the order list (row actions) and the order page (header buttons), so both offer exactly the
 * same moves with the same checks.
 *
 * Fulfilment is done by the client, not by a shipping integration, so each action simply
 * records the new status — but only when the order is allowed to make that move
 * (Order::allowedTransitions), and always with who did it and when.
 */
class OrderStatusActions
{
    /** @return array<int, Action> dispatch, deliver, cancel, advance — each hides itself when it does not apply */
    public static function all(): array
    {
        return [
            self::dispatch(),
            self::deliver(),
            self::cancel(),
            self::advance(),
        ];
    }

    public static function dispatch(): Action
    {
        return Action::make('dispatch')
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
            ]));
    }

    public static function deliver(): Action
    {
        return Action::make('deliver')
            ->label('Delivered')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->authorize(fn (Order $record) => self::mayFulfil($record))
            ->visible(fn (Order $record) => $record->canTransitionTo(Order::STATUS_DELIVERED))
            ->requiresConfirmation()
            ->modalDescription('Records this order as delivered. This closes the order.')
            ->schema([TextInput::make('note')->label('Note')->placeholder('Who received it, for example')])
            ->action(fn (Order $record, array $data) => self::move($record, Order::STATUS_DELIVERED, $data['note'] ?? null));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->authorize(fn (Order $record) => self::mayFulfil($record))
            ->visible(fn (Order $record) => $record->canTransitionTo(Order::STATUS_CANCELLED))
            ->schema([
                Textarea::make('reason')->label('Reason')->required()->rows(2)
                    ->helperText('Recorded with the order and shown on the timeline.'),
            ])
            ->action(fn (Order $record, array $data) => self::move($record, Order::STATUS_CANCELLED, $data['reason']));
    }

    /** The full pipeline, for the steps between dispatch and delivery. */
    public static function advance(): Action
    {
        return Action::make('advance')
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
            ]));
    }

    /** Only an account the policy lets edit this order may change its status. */
    public static function mayFulfil(Order $order): bool
    {
        return (bool) filament()->auth()->user()?->can('update', $order);
    }

    /**
     * Applies one status change. The transition is checked again here, so an action triggered
     * from a page that was open while the order moved on is refused rather than applied.
     */
    public static function move(Order $order, string $status, ?string $note = null, array $attributes = []): void
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
}
