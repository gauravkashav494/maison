<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Support\GstSupply;
use App\Support\GuestCustomer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns the cart into an order: the order and its lines, stock, the coupon count and, when
 * asked, the address saved to the customer's account. The cart is emptied afterwards.
 *
 * Used by the checkout form for a signed-in customer, and after the WhatsApp code for a
 * shopper who checked out without an account. A sales person's order comes here prepared by
 * SalesOrder::prepare(): the store's address, the customer it is for and `salesperson_id`.
 */
class PlaceOrder
{
    public function __construct(private readonly Cart $cart) {}

    /**
     * @param  array  $data  The validated checkout form (CheckoutRequest).
     *
     * @throws InsufficientStockException when a line asks for more than is left (no order is made
     *                                    and the cart is lowered to what is left), or the cart still
     *                                    holds lines no longer sold (none available: nothing lowered).
     */
    public function __invoke(array $data, ?Customer $customer, bool $saveAddress = false): Order
    {
        $this->cart->setShippingMethod($data['shipping_method']);
        $cart = $this->cart->toArray();
        // Lines no longer sold must be removed by the shopper first (the checkout page lists them):
        // no order is made, and the callers send the shopper back to checkout as for stock.
        if ($cart['unavailable'] !== []) {
            throw new InsufficientStockException([]);
        }
        // A sales order is handed over at the store: no cash-on-delivery handling fee.
        $salesOrder = filled($data['salesperson_id'] ?? null);
        $codFee = $data['payment_method'] === 'cod' && ! $salesOrder ? (float) setting('checkout.cod_fee', 0) : 0.0;

        // Orders, items and stock live on the client connection; addresses and coupons may live on
        // the central one. Wrap both so a late failure doesn't leave a half-placed order behind.
        $clientConnection = DB::connection((new Order)->getConnectionName());
        try {
            $order = $clientConnection->transaction(fn () => DB::transaction(fn () => $this->place($data, $cart, $codFee, $customer, $saveAddress)));
        } catch (InsufficientStockException $e) {
            // Sold by someone else (or in another window) since this cart was filled.
            $this->cart->limitToStock($e->available);

            throw $e;
        }

        $this->cart->clear();

        return $order;
    }

    private function place(array $data, array $cart, float $codFee, ?Customer $customer, bool $saveAddress): Order
    {
        // The products are locked until the order is committed, so two checkouts for the last few
        // units cannot both read the same stock: the second waits, then sees what the first left.
        $products = $this->lockAndCheckStock($cart['items']);
        $salesOrder = filled($data['salesperson_id'] ?? null);

        $order = Order::create([
            'number' => Order::generateNumber(),
            'template' => template()->id(),
            // A guest's order is kept against a customer record for their email (when they give one), as on most stores.
            'customer_id' => GuestCustomer::idFor($customer, $data['email'] ?? null, $data['shipping_name'], $data['phone']),
            'salesperson_id' => $data['salesperson_id'] ?? null,
            'status' => 'confirmed',
            'email' => filled($data['email'] ?? null) ? strtolower($data['email']) : null,
            'phone' => $data['phone'],
            'shipping_name' => $data['shipping_name'],
            'shipping_line1' => $data['shipping_line1'],
            'shipping_line2' => $data['shipping_line2'] ?? null,
            'shipping_city' => $data['shipping_city'],
            'shipping_state' => $data['shipping_state'],
            'shipping_postal_code' => $data['shipping_postal_code'],
            'shipping_country' => $data['shipping_country'],
            'shipping_method' => $cart['shipping_method']['name'],
            'shipping_cost' => $cart['shipping'] + $codFee,
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_method'] === 'cod' ? 'cod' : 'paid',
            'subtotal' => $cart['subtotal'],
            'discount' => $cart['discount'],
            'coupon_code' => $cart['coupon']['code'] ?? null,
            'tax' => $cart['tax'],
            'tax_rate' => $cart['tax_rate'],
            // GST becomes IGST or CGST + SGST by where the order is shipped.
            'tax_label' => GstSupply::labelFor($data['shipping_state'], $data['shipping_country']),
            'prices_include_tax' => $cart['tax_inclusive'],
            'total' => $cart['total'] + $codFee,
            'estimated_delivery' => $salesOrder ? null : now()->addDays(5),
            'notes' => $data['notes'] ?? null,
            'status_history' => [['status' => 'confirmed', 'at' => now()->toIso8601String(), 'note' => $salesOrder ? 'Order placed by sales' : 'Order placed']],
        ]);

        foreach ($cart['items'] as $line) {
            $order->items()->create([
                'product_id' => $line['product']['id'],
                'name' => $line['product']['name'],
                'sku' => $products[$line['product']['id']]->sku,
                'image' => $line['product']['images'][0] ?? null,
                'size' => $line['size'],
                'color' => $line['color'],
                // What the customer chose, recorded with the order rather than inferred later.
                'options' => $line['options'] ?: null,
                'variant_sku' => $line['variant_sku'] ?? null,
                'price' => $line['unit_price'] ?? $line['product']['price'],
                'qty' => $line['qty'],
                'total' => $line['line_total'],
                // The product's rate and the tax on this line, as charged now.
                'tax_rate' => $line['tax_rate'],
                'tax' => $line['tax'],
            ]);
            $products[$line['product']['id']]->adjustStock(-(int) $line['qty'], $line['options'] ?? []);
        }

        // The invoice number is taken with the order, so the series follows the order in which orders were placed.
        $order->assignInvoiceNumber();

        if (! empty($cart['coupon']['code'])) {
            Coupon::where('code', $cart['coupon']['code'])->increment('used_count');
        }

        if ($customer && $saveAddress && ! $salesOrder) {
            $customer->addresses()->create([
                'name' => $data['shipping_name'],
                'phone' => $data['phone'],
                'line1' => $data['shipping_line1'],
                'line2' => $data['shipping_line2'] ?? null,
                'city' => $data['shipping_city'],
                'state' => $data['shipping_state'],
                'postal_code' => $data['shipping_postal_code'],
                'country' => $data['shipping_country'],
                'is_default' => $customer->addresses()->count() === 0,
            ]);
        }

        return $order;
    }

    /**
     * Locks the cart's products until the order is committed and checks every line against the
     * stock as it is now (Product::lockForLines).
     *
     * @return Collection<int, Product> keyed by id
     *
     * @throws InsufficientStockException
     */
    private function lockAndCheckStock(array $items): Collection
    {
        $lines = [];
        foreach ($items as $line) {
            $lines[$line['key']] = ['product_id' => $line['product']['id'], 'options' => $line['options'] ?? [], 'qty' => (int) $line['qty']];
        }
        ['products' => $products, 'short' => $short] = Product::lockForLines($lines);

        if ($short) {
            throw new InsufficientStockException($short);
        }

        return $products;
    }
}
