<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\StaffAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets the customer cancel their own order while it has not been dispatched yet
 * (Order::customerCanCancel), from their account's order page or from the confirmation page.
 *
 * Allowed for the customer who owns the order and for the guest who has just placed it — the
 * same rule as the confirmation page. The cancellation goes through the same transition and
 * audit trail as one by staff, so the goods go back into stock (Order::setStatus).
 */
class CancelOrderController extends Controller
{
    public function __invoke(Request $request, string $number): RedirectResponse
    {
        $order = Order::where('number', $number)->firstOrFail();
        $customer = $request->user();

        abort_unless(
            session('last_order_number') === $order->number || ($customer && (int) $order->customer_id === (int) $customer->id),
            403,
        );

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $back = back(fallback: route('orders.confirmation', $order->number));

        if (! $order->customerCanCancel()) {
            return $back->withErrors(['cancel' => 'This order can no longer be cancelled online. Please contact us for help.']);
        }

        $reason = trim('Cancelled by customer'.(filled($data['reason'] ?? null) ? ': '.$data['reason'] : ''));
        if ($order->transitionTo(Order::STATUS_CANCELLED, $reason, $customer)) {
            StaffAlerts::orderCancelledByCustomer($order);
        }

        return $back->with('order_cancelled', $order->payment_status === 'paid'
            ? "Order {$order->number} has been cancelled. Your refund will be processed to the original payment method."
            : "Order {$order->number} has been cancelled.");
    }
}
