<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Fulfilment')
                    ->description('Changing the status appends an entry to the order timeline the customer sees.')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(Order::STATUSES + ['cancelled' => 'Cancelled'])
                            ->required()
                            ->native(false),
                        Select::make('payment_status')
                            ->options(['pending' => 'Pending', 'paid' => 'Paid', 'cod' => 'Cash on delivery', 'refunded' => 'Refunded'])
                            ->required()
                            ->native(false),
                        TextInput::make('carrier')->placeholder('Blue Dart, Delhivery, DHL…'),
                        TextInput::make('tracking_number'),
                        DatePicker::make('estimated_delivery')->native(false),
                        TextInput::make('status_note')
                            ->label('Note for this status change (optional)')
                            ->dehydrated(false)
                            ->placeholder('e.g. Left our Bengaluru warehouse'),
                    ]),
                Section::make('Delivery address')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        TextInput::make('shipping_name')->required(),
                        TextInput::make('phone'),
                        TextInput::make('shipping_line1')->label('Address line 1')->required()->columnSpanFull(),
                        TextInput::make('shipping_line2')->label('Address line 2')->columnSpanFull(),
                        TextInput::make('shipping_city')->required(),
                        TextInput::make('shipping_state')->required(),
                        TextInput::make('shipping_postal_code')->required(),
                        TextInput::make('shipping_country')->required(),
                    ]),
                Grid::make(1)->schema([
                    Textarea::make('notes')->label('Internal / customer notes')->rows(3),
                ]),
            ]);
    }
}
