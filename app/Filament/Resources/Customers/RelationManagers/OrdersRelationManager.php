<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Filament\Support\RowActions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer's orders on their detail page. Customers live in the platform database but orders
 * live in their client's database, so the list only shows while that client is the one
 * selected (always true for the client's own team).
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Orders';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class)
            && self::ordersReachable($ownerRecord)
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    /** With one database per client, the customer's orders can be read only inside that client. */
    public static function ordersReachable(Model $customer): bool
    {
        return ! app(ClientDatabase::class)->isolated()
            || (int) app(ClientContext::class)->id() === (int) $customer->client_id;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('Order')->weight('bold')->searchable(),
                TextColumn::make('created_at')->label('Placed')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('items_count')->counts('items')->label('Items')->alignRight(),
                TextColumn::make('total')->formatStateUsing(fn (float $state) => money($state))->sortable()->alignRight(),
                TextColumn::make('payment_method')->formatStateUsing(fn ($state) => Order::PAYMENT_METHODS[$state] ?? $state)->badge()->color('gray'),
                TextColumn::make('status')->formatStateUsing(fn ($state, Order $r) => $r->statusLabel())->badge()
                    // Same colours as the orders list.
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'info', 'processing' => 'warning', 'shipped', 'out_for_delivery' => 'primary', 'delivered' => 'success', 'cancelled' => 'danger', default => 'gray',
                    }),
            ])
            ->filters([SelectFilter::make('status')->options(Order::statusOptions())->multiple()->native(false)])
            ->recordActions([RowActions::group([
                Action::make('view')->label('View')->icon('heroicon-m-eye')
                    ->url(fn (Order $r) => OrderResource::getUrl('view', ['record' => $r]))
                    ->visible(fn (Order $r) => OrderResource::canView($r)),
            ])])
            ->emptyStateHeading('No orders yet');
    }
}
