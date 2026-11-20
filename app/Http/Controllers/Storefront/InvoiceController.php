<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Invoice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads an order's invoice as a PDF.
 *
 * Allowed for the customer who owns the order, for the guest who has just placed it (the same
 * rule as the confirmation page), and for anyone holding a signed link from Order::invoiceUrl()
 * (the tracking page hands one out after the order number and email have been checked). Not
 * found until the order is dispatched: the invoice is issued then.
 */
class InvoiceController extends Controller
{
    public function __invoke(Request $request, string $number): Response
    {
        $order = Order::with('items')->where('number', $number)->firstOrFail();

        abort_unless(
            $request->hasValidSignature()
                || session('last_order_number') === $order->number
                || ($request->user() && (int) $order->customer_id === (int) $request->user()->id),
            403,
        );
        // Not issued until the order is dispatched (Order::hasInvoice).
        abort_unless($order->hasInvoice(), 404);

        return Invoice::for($order)->download();
    }
}
