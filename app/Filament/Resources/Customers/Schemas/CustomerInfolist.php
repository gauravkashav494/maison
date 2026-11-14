<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Clients\ClientContext;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Models\Customer;
use App\Models\Order;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only overview of a shopper: contact details and saved addresses on the left, their
 * account and what they have spent on the right. Their orders are listed below the page.
 */
class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make('Customer')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->weight('bold')->size('lg')->columnSpanFull(),
                        TextEntry::make('email')->icon('heroicon-m-envelope')->copyable()
                            ->url(fn (Customer $r) => 'mailto:'.$r->email),
                        TextEntry::make('phone')->icon('heroicon-m-phone')->copyable()->placeholder('—')
                            ->url(fn (Customer $r) => $r->phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $r->phone) : null),
                    ]),

                Section::make('Addresses')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        RepeatableEntry::make('addresses')->hiddenLabel()
                            ->schema([
                                TextEntry::make('label')->hiddenLabel()->weight('bold')->placeholder('Address')
                                    ->suffix(fn ($record) => $record->is_default ? ' · Default' : null),
                                TextEntry::make('lines')->hiddenLabel()
                                    ->state(fn ($record) => implode("\n", array_filter([
                                        $record->name,
                                        $record->line1,
                                        $record->line2,
                                        trim($record->city.', '.$record->state.' '.$record->postal_code, ', '),
                                        $record->country,
                                    ])))
                                    ->extraAttributes(['style' => 'white-space: pre-line']),
                                TextEntry::make('phone')->hiddenLabel()->icon('heroicon-m-phone')->placeholder('—'),
                            ])
                            ->grid(2)
                            ->placeholder('No saved addresses'),
                    ]),
            ]),

            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make('Orders')
                    ->icon('heroicon-o-shopping-cart')
                    ->description('Excluding cancelled orders.')
                    ->visible(fn (Customer $r) => OrdersRelationManager::ordersReachable($r))
                    ->schema([
                        TextEntry::make('orders_count')->label('Orders')->inlineLabel()->state(fn (Customer $r) => self::orders($r)['count']),
                        TextEntry::make('spent')->label('Total spent')->inlineLabel()->weight('bold')->state(fn (Customer $r) => money(self::orders($r)['spent'])),
                        TextEntry::make('average')->label('Average order')->inlineLabel()
                            ->state(fn (Customer $r) => self::orders($r)['count'] ? money(self::orders($r)['spent'] / self::orders($r)['count']) : null)
                            ->placeholder('—'),
                        TextEntry::make('last_order')->label('Last order')->inlineLabel()->state(fn (Customer $r) => self::orders($r)['last'])
                            ->since()->placeholder('Never'),
                    ]),

                Section::make('Account')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        IconEntry::make('is_active')->label('Can sign in')->inlineLabel()->boolean(),
                        IconEntry::make('email_verified')->label('Email verified')->inlineLabel()->boolean()
                            ->state(fn (Customer $r) => $r->email_verified_at !== null),
                        TextEntry::make('client.name')->label('Client')->inlineLabel()->badge()->color('gray')
                            ->visible(fn () => app(ClientContext::class)->isSuperAdmin()),
                        TextEntry::make('created_at')->label('Joined')->inlineLabel()->date('d M Y'),
                        TextEntry::make('deleted_at')->label('Deleted')->inlineLabel()->dateTime('d M Y, H:i')->color('danger')
                            ->visible(fn (Customer $r) => $r->trashed()),
                    ]),
            ]),
        ]);
    }

    /** @return array{count:int, spent:float, last:?string} */
    private static function orders(Customer $customer): array
    {
        return once(function () use ($customer) {
            $orders = Order::query()->where('customer_id', $customer->getKey())->where('status', '!=', Order::STATUS_CANCELLED);

            return [
                'count' => (clone $orders)->count(),
                'spent' => (float) (clone $orders)->sum('total'),
                'last' => (clone $orders)->max('created_at'),
            ];
        });
    }
}
