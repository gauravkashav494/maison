<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Forms\Components\PhoneInput;
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
        // A sales order (placed by a sales person) carries no address, so none is asked for. The
        // columns are not nullable: a blank field is saved as an empty string.
        $needsAddress = fn (?Order $record) => ! $record?->salesperson_id;

        return $schema
            ->columns(1)
            ->components([
                Section::make('Fulfilment')
                    ->description('Changing the status appends an entry to the order timeline the customer sees.')
                    ->columns(2)
                    ->schema([
                        // Forward only: the current status and the steps after it (Order::allowedTransitions()).
                        // A delivered or cancelled order is finished, so its status is locked.
                        Select::make('status')
                            ->options(fn (?Order $record) => $record
                                ? array_intersect_key(Order::statusOptions(), array_flip([$record->status, ...$record->allowedTransitions()]))
                                : Order::statusOptions())
                            ->disabled(fn (?Order $record) => $record && $record->allowedTransitions() === [])
                            ->helperText(fn (?Order $record) => $record && $record->allowedTransitions() === []
                                ? 'This order is '.strtolower($record->statusLabel()).', so its status can no longer change.'
                                : 'An order only moves forward; it cannot go back to an earlier status.')
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
                        PhoneInput::make('phone'),
                        TextInput::make('shipping_line1')->label('Address line 1')->required($needsAddress)->dehydrateStateUsing(fn ($state) => $state ?? '')->columnSpanFull(),
                        TextInput::make('shipping_line2')->label('Address line 2')->columnSpanFull(),
                        TextInput::make('shipping_city')->required($needsAddress)->dehydrateStateUsing(fn ($state) => $state ?? ''),
                        TextInput::make('shipping_state')->required($needsAddress)->dehydrateStateUsing(fn ($state) => $state ?? ''),
                        TextInput::make('shipping_postal_code')->required($needsAddress)->dehydrateStateUsing(fn ($state) => $state ?? ''),
                        TextInput::make('shipping_country')->required($needsAddress)->dehydrateStateUsing(fn ($state) => $state ?? ''),
                    ]),
                Grid::make(1)->schema([
                    Textarea::make('notes')->label('Internal / customer notes')->rows(3),
                ]),
            ]);
    }
}
