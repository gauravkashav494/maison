<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Widgets\Widget;

/** Latest orders with a "View all" link, in the style of an app dashboard list. */
class RecentOrders extends Widget
{
    protected string $view = 'filament.widgets.recent-orders';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected function getViewData(): array
    {
        return [
            'orders' => Order::withCount('items')->latest()->limit(6)->get(),
            'indexUrl' => OrderResource::getUrl('index'),
            'viewUrl' => fn (Order $order) => OrderResource::getUrl('view', ['record' => $order]),
            'pill' => fn (string $status) => match ($status) {
                'delivered' => 'is-green',
                'shipped', 'out_for_delivery' => 'is-blue',
                'confirmed', 'processing' => 'is-amber',
                'cancelled' => 'is-red',
                default => 'is-gray',
            },
        ];
    }
}
