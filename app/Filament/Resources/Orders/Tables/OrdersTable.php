<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(Order::STATUSES + ['cancelled' => 'Cancelled']),
                SelectFilter::make('payment_method')->options(Order::PAYMENT_METHODS),
            ])
            ->recordActions([
                Action::make('advance')
                    ->label('Update status')
                    ->icon('heroicon-o-truck')
                    ->schema([
                        Select::make('status')->options(Order::STATUSES + ['cancelled' => 'Cancelled'])->required()->native(false)
                            ->default(fn (Order $record) => array_keys(Order::STATUSES)[min($record->statusIndex() + 1, count(Order::STATUSES) - 1)] ?? 'confirmed'),
                        TextInput::make('tracking_number')->default(fn (Order $record) => $record->tracking_number),
                        TextInput::make('note')->placeholder('Optional note shown on the timeline'),
                    ])
                    ->action(function (Order $record, array $data) {
                        if (filled($data['tracking_number'] ?? null)) {
                            $record->update(['tracking_number' => $data['tracking_number']]);
                        }
                        $record->setStatus($data['status'], $data['note'] ?? null);
                    }),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
