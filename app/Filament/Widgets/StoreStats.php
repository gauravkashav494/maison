<?php

namespace App\Filament\Widgets;

use App\Admin\StoreContext;
use App\Clients\ClientAccess;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Filament\Widgets\Widget;

/** A client's headline numbers. A figure is neither computed nor sent unless the role grants it. */
class StoreStats extends Widget
{
    protected string $view = 'filament.widgets.store-stats';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $context = app(StoreContext::class);

        return $context->template() !== null
            && ! $context->templateObject()?->supportsServices()
            && (ClientAccess::current('orders', 'view') || ClientAccess::current('customers', 'view'));
    }

    protected function getViewData(): array
    {
        $stats = [];

        if (ClientAccess::current('orders', 'view')) {
            $monthStart = now()->startOfMonth();
            $open = Order::whereIn('status', ['confirmed', 'processing'])->count();

            $stats[] = [
                'label' => 'Orders this month',
                'value' => Order::where('created_at', '>=', $monthStart)->count(),
                'description' => Order::count().' all time',
                'icon' => 'shopping-cart',
                'color' => 'is-blue',
                'url' => OrderResource::getUrl('index'),
            ];
            $stats[] = [
                'label' => 'Revenue this month',
                'value' => money((int) Order::where('created_at', '>=', $monthStart)->where('status', '!=', 'cancelled')->sum('total')),
                'description' => 'Excluding cancelled orders',
                'icon' => 'banknotes',
                'color' => 'is-green',
                'url' => OrderResource::getUrl('index'),
            ];
            $stats[] = [
                'label' => 'Orders to fulfil',
                'value' => $open,
                'description' => 'Confirmed or processing',
                'icon' => 'truck',
                'color' => 'is-orange',
                'url' => OrderResource::getUrl('index'),
            ];
        }

        if (ClientAccess::current('customers', 'view')) {
            $lowStock = ClientAccess::current('products', 'view') ? Product::active()->where('stock', '<=', 5)->count() : null;

            $stats[] = [
                'label' => 'Customers',
                'value' => Customer::where('client_id', app(StoreContext::class)->user()?->clientId())->count(),
                'description' => $lowStock === null ? 'Registered shoppers' : $lowStock.' product'.($lowStock === 1 ? '' : 's').' low on stock',
                'icon' => 'users',
                'color' => 'is-purple',
                'url' => CustomerResource::getUrl('index'),
            ];
        }

        return ['stats' => $stats];
    }
}
