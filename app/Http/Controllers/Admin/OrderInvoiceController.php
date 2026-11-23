<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\Orders\OrderResource;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Activity\ActivityLog;
use App\Support\Invoice;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opens an order's invoice PDF in the browser for the store admin ("View invoice"). Registered
 * on the client panel, so it runs behind the panel's login and store scope: an order of another
 * store is simply not found.
 */
class OrderInvoiceController extends Controller
{
    public function __invoke(int $order): Response
    {
        $order = Order::with('items')->findOrFail($order);
        abort_unless(OrderResource::canView($order), 403);
        abort_unless($order->hasInvoice(), 404);
        app(ActivityLog::class)->record('viewed', 'opened the invoice of '.app(ActivityLog::class)->describer()->reference($order), $order);

        return Invoice::for($order)->stream();
    }
}
