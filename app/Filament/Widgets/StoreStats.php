<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\Widget;

/** Dashboard headline numbers: orders, revenue, customers and stock, each linking to its resource. */
class StoreStats extends Widget
{
    protected string $view = 'filament.widgets.store-stats';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $context = app(\App\Admin\StoreContext::class);

        return $context->template() === null ? $context->isSuperAdmin() : ! $context->templateObject()?->supportsServices();
    }

    protected function getViewData(): array
    {
        $monthStart = now()->startOfMonth();
        $open = Order::whereIn('status', ['confirmed', 'processing'])->count();
        $lowStock = Product::active()->where('stock', '<=', 5)->count();

        return [
            'stats' => [
                [
                    'label' => 'Orders this month',
                    'value' => Order::where('created_at', '>=', $monthStart)->count(),
                    'description' => Order::count().' all time',
                    'icon' => 'shopping-cart',
                    'color' => 'is-blue',
                    'url' => OrderResource::getUrl('index'),
                ],
                [
                    'label' => 'Revenue this month',
                    'value' => money((int) Order::where('created_at', '>=', $monthStart)->where('status', '!=', 'cancelled')->sum('total')),
                    'description' => 'Excluding cancelled orders',
                    'icon' => 'banknotes',
                    'color' => 'is-green',
                    'url' => OrderResource::getUrl('index'),
                ],
                [
                    'label' => 'Orders to fulfil',
                    'value' => $open,
                    'description' => $open === 1 ? 'Confirmed or processing' : 'Confirmed or processing',
                    'icon' => 'truck',
                    'color' => 'is-orange',
                    'url' => OrderResource::getUrl('index'),
                ],
                [
                    'label' => 'Customers',
                    'value' => User::whereNull('role')->count(),
                    'description' => $lowStock.' product'.($lowStock === 1 ? '' : 's').' low on stock',
                    'icon' => 'users',
                    'color' => 'is-purple',
                    'url' => UserResource::getUrl('index'),
                    'description_url' => ProductResource::getUrl('index'),
                ],
            ],
        ];
    }
}
