<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Items')
                    ->columnSpan(2)
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                ImageEntry::make('image_url')->hiddenLabel()->square()->imageSize(56),
                                TextEntry::make('name')->hiddenLabel()->weight('bold')
                                    ->helperText(fn ($record) => trim(($record->color ? $record->color.' · ' : '').'Size '.$record->size.($record->sku ? ' · '.$record->sku : ''))),
                                TextEntry::make('qty')->label('Qty')->alignRight(),
                                TextEntry::make('total')->label('Total')->formatStateUsing(fn (int $state) => money($state))->alignRight(),
                            ])
                            ->columns(4),
                        Grid::make(2)->schema([
                            TextEntry::make('subtotal')->formatStateUsing(fn (int $state) => money($state)),
                            TextEntry::make('discount')->formatStateUsing(fn (int $state, Order $r) => money($state).($r->coupon_code ? " ({$r->coupon_code})" : ''))->visible(fn (Order $r) => $r->discount > 0),
                            TextEntry::make('shipping_cost')->label('Shipping')->formatStateUsing(fn (int $state) => money($state)),
                            TextEntry::make('tax')->formatStateUsing(fn (int $state) => money($state))->visible(fn (Order $r) => $r->tax > 0),
                            TextEntry::make('total')->formatStateUsing(fn (int $state) => money($state))->weight('bold')->size('lg'),
                        ]),
                    ]),

                Section::make('Customer & delivery')
                    ->schema([
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('phone')->copyable(),
                        TextEntry::make('user.name')->label('Account')->placeholder('Guest checkout'),
                        TextEntry::make('shipping_address')->label('Ships to')->state(fn (Order $r) => implode("\n", $r->shippingAddressLines()))->html(false),
                        TextEntry::make('shipping_method'),
                        TextEntry::make('payment_method')->formatStateUsing(fn ($state) => Order::PAYMENT_METHODS[$state] ?? $state),
                        TextEntry::make('payment_status')->badge()->color(fn ($state) => match ($state) { 'paid' => 'success', 'cod' => 'warning', 'refunded' => 'gray', default => 'danger' }),
                        TextEntry::make('notes')->placeholder('—'),
                        TextEntry::make('created_at')->label('Placed')->dateTime('d M Y, H:i'),
                    ]),

                Section::make('Status history')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('status_history')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('status')->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? ucfirst($state))->badge(),
                                TextEntry::make('at')->dateTime('d M Y, H:i'),
                                TextEntry::make('note')->placeholder('—'),
                            ])
                            ->columns(3),
                    ]),
            ]);
    }
}
