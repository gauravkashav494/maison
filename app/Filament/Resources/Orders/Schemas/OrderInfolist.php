<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Str;

/**
 * The order page body, in the usual store-admin layout:
 *
 *   main column — Items, Payment (read like a receipt), Order timeline
 *   side column — Customer, Shipping address, Delivery, Cancelled, Customer note
 *
 * The status and payment badges and the fulfilment buttons sit in the page header (ViewOrder).
 * Side cards hide rows that have nothing to show; their labels are bold (admin theme,
 * .fi-order-side).
 */
class OrderInfolist
{
    /** Shown for an item without a picture, so every row lines up. */
    private const NO_IMAGE = 'data:image/svg+xml,'.'%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 48 48%22%3E%3Crect width=%2248%22 height=%2248%22 rx=%228%22 fill=%22%23eef1f5%22/%3E%3Cpath d=%22M16 19l8-4 8 4v10l-8 4-8-4z%22 fill=%22none%22 stroke=%22%2394a3b8%22 stroke-width=%221.6%22 stroke-linejoin=%22round%22/%3E%3Cpath d=%22M16 19l8 4 8-4M24 23v10%22 fill=%22none%22 stroke=%22%2394a3b8%22 stroke-width=%221.6%22/%3E%3C/svg%3E';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'lg' => 3])
            ->components([
                Grid::make(1)
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([self::items(), self::payment(), self::timeline()]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->extraAttributes(['class' => 'fi-order-side'])
                    ->schema([self::customer(), self::address(), self::delivery(), self::cancellation(), self::notes()]),
            ]);
    }

    private static function items(): Section
    {
        return Section::make('Items')
            ->description(fn (Order $r) => $r->items->count().' '.Str::plural('line', $r->items->count()).' · '.$r->items->sum('qty').' '.Str::plural('unit', $r->items->sum('qty')))
            ->icon('heroicon-o-shopping-bag')
            ->schema([
                RepeatableEntry::make('items')
                    ->hiddenLabel()
                    ->contained(false)
                    ->schema([
                        Grid::make(['default' => 12])->schema([
                            ImageEntry::make('image_url')->hiddenLabel()->square()->imageSize(48)->columnSpan(['default' => 2, 'sm' => 1])
                                ->defaultImageUrl(self::NO_IMAGE),
                            TextEntry::make('name')->hiddenLabel()->weight(FontWeight::SemiBold)->columnSpan(['default' => 10, 'sm' => 7])
                                ->helperText(fn ($record) => trim(implode(' · ', array_filter([$record->optionsLabel(), $record->variant_sku ?: $record->sku])))),
                            TextEntry::make('qty')->hiddenLabel()->alignRight()->columnSpan(['default' => 6, 'sm' => 2])->color('gray')
                                ->formatStateUsing(fn ($state, $record) => money($record->price).' × '.$state),
                            TextEntry::make('total')->hiddenLabel()->alignRight()->columnSpan(['default' => 6, 'sm' => 2])->weight(FontWeight::SemiBold)
                                ->formatStateUsing(fn (float $state) => money($state)),
                        ]),
                    ]),
            ]);
    }

    /** Subtotal down to the total, then how it was paid — top to bottom like a receipt. */
    private static function payment(): Section
    {
        $row = fn (string $name) => TextEntry::make($name)->inlineLabel()->alignRight();

        return Section::make('Payment')
            ->icon('heroicon-o-banknotes')
            ->schema([
                $row('subtotal')
                    ->label(fn (Order $r) => 'Subtotal ('.$r->items->sum('qty').' '.Str::plural('unit', $r->items->sum('qty')).')')
                    ->formatStateUsing(fn (float $state) => money($state)),
                $row('discount')
                    ->label(fn (Order $r) => $r->coupon_code ? "Discount ({$r->coupon_code})" : 'Discount')
                    ->formatStateUsing(fn (float $state) => '−'.money($state))
                    ->color('success')
                    ->visible(fn (Order $r) => $r->discount > 0),
                $row('shipping_cost')
                    ->label(fn (Order $r) => $r->shipping_method ? 'Shipping · '.$r->shipping_method : 'Shipping')
                    ->formatStateUsing(fn (float $state) => $state > 0 ? money($state) : 'Free'),
                $row('tax')
                    ->label('Tax')
                    ->formatStateUsing(fn (float $state) => money($state))
                    ->visible(fn (Order $r) => $r->tax > 0),
                $row('total')
                    ->label('Total')
                    ->formatStateUsing(fn (float $state) => money($state))
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->extraEntryWrapperAttributes(['class' => 'fi-order-total']),
                $row('payment_method')
                    ->label(fn (Order $r) => $r->payment_status === 'paid' ? 'Paid by' : 'Payment')
                    // "Cash on delivery · Pay on delivery" says one thing twice, so COD shows the method alone.
                    ->state(fn (Order $r) => (Order::PAYMENT_METHODS[$r->payment_method] ?? ($r->payment_method ?: '—'))
                        .($r->payment_status === 'cod' ? '' : ' · '.$r->paymentStatusLabel())),
            ]);
    }

    private static function timeline(): Section
    {
        return Section::make('Order timeline')
            ->icon('heroicon-o-clock')
            ->schema([
                ViewEntry::make('status_history')->hiddenLabel()->view('filament.orders.status-timeline'),
            ]);
    }

    private static function customer(): Section
    {
        return Section::make('Customer')
            ->icon('heroicon-o-user')
            ->schema([
                TextEntry::make('customer_name')->hiddenLabel()->weight(FontWeight::SemiBold)
                    ->state(fn (Order $r) => $r->customer?->name ?: $r->shipping_name)
                    ->helperText(fn (Order $r) => self::history($r)),
                TextEntry::make('email')->icon('heroicon-m-envelope')->copyable()->copyMessage('Email copied'),
                TextEntry::make('phone')->icon('heroicon-m-phone')->copyable()->copyMessage('Phone copied')
                    ->visible(fn (Order $r) => filled($r->phone)),
            ]);
    }

    /**
     * How often this customer has ordered — matched by email, so guest checkouts count too.
     * Cancelled orders are left out of both figures.
     */
    private static function history(Order $order): string
    {
        $base = Order::query()->where('email', $order->email)->where('status', '!=', Order::STATUS_CANCELLED);
        $count = (clone $base)->count();
        $account = $order->customer_id ? 'Has an account' : 'Guest checkout';

        if ($count <= 1) {
            return $account.' · First order';
        }

        return $account.' · '.$count.' orders · '.money((float) (clone $base)->sum('total')).' spent';
    }

    private static function address(): Section
    {
        return Section::make('Shipping address')
            ->icon('heroicon-o-map-pin')
            ->schema([
                TextEntry::make('shipping_address')->hiddenLabel()
                    ->state(fn (Order $r) => implode('<br>', array_map('e', $r->shippingAddressLines())))
                    ->html()
                    ->copyable()->copyMessage('Address copied')
                    ->copyableState(fn (Order $r) => implode(', ', $r->shippingAddressLines())),
            ]);
    }

    private static function delivery(): Section
    {
        return Section::make('Delivery')
            ->icon('heroicon-o-truck')
            ->schema([
                TextEntry::make('shipping_method')->label('Method')->placeholder('—'),
                TextEntry::make('carrier')->visible(fn (Order $r) => filled($r->carrier)),
                TextEntry::make('tracking_number')->label('Tracking number')->copyable()->copyMessage('Tracking number copied')
                    ->visible(fn (Order $r) => filled($r->tracking_number)),
                TextEntry::make('estimated_delivery')->label('Expected by')->date('d M Y')
                    ->visible(fn (Order $r) => $r->estimated_delivery && ! $r->delivered_at && ! $r->isCancelled()),
                TextEntry::make('dispatched_at')->label('Dispatched')->dateTime('d M Y, H:i')
                    ->visible(fn (Order $r) => $r->dispatched_at !== null),
                TextEntry::make('delivered_at')->label('Delivered')->dateTime('d M Y, H:i')
                    ->visible(fn (Order $r) => $r->delivered_at !== null),
            ]);
    }

    private static function cancellation(): Section
    {
        return Section::make('Cancelled')
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->visible(fn (Order $r) => $r->isCancelled())
            ->schema([
                TextEntry::make('cancellation_reason')->label('Reason')->placeholder('No reason recorded'),
                TextEntry::make('cancelled_at')->label('When')->dateTime('d M Y, H:i')->placeholder('—'),
            ]);
    }

    private static function notes(): Section
    {
        return Section::make('Customer note')
            ->icon('heroicon-o-chat-bubble-bottom-center-text')
            ->visible(fn (Order $r) => filled($r->notes))
            ->schema([
                TextEntry::make('notes')->hiddenLabel()->html()->formatStateUsing(fn (?string $state) => nl2br(e((string) $state))),
            ]);
    }
}
