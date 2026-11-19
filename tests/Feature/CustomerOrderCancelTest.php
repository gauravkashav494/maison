<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Customers can cancel their own order from their account until it is dispatched, and a
 * cancelled order's goods go back into stock, whoever cancels it.
 */
class CustomerOrderCancelTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha Rao', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);
    }

    private function order(string $status, ?Customer $customer = null, string $paymentStatus = 'cod'): Order
    {
        $customer ??= $this->customer;

        return Order::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'customer_id' => $customer->id, 'number' => 'ME-'.strtoupper(uniqid()),
            'email' => $customer->email, 'status' => $status, 'payment_method' => 'cod', 'payment_status' => $paymentStatus,
            'shipping_name' => 'Asha Rao', 'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai',
            'shipping_state' => 'MH', 'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'subtotal' => 1000, 'total' => 1000,
        ]);
    }

    private function asCustomer(?Customer $customer = null)
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha'])->actingAs($customer ?? $this->customer, 'web');
    }

    public static function cancellable(): array
    {
        return [['confirmed'], ['processing']];
    }

    #[DataProvider('cancellable')]
    public function test_the_customer_cancels_an_order_that_has_not_been_dispatched(string $status): void
    {
        $order = $this->order($status);

        $this->asCustomer()->get(route('account.order', $order->number))->assertSee('Cancel order');

        $page = route('account.order', $order->number);
        $this->asCustomer()->from($page)->post(route('orders.cancel', $order->number), ['reason' => 'Ordered the wrong size'])
            ->assertRedirect($page)
            ->assertSessionHas('order_cancelled');

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertSame('Cancelled by customer: Ordered the wrong size', $order->cancellation_reason);
        $this->assertSame(Customer::class, last($order->status_history)['by_type']);
    }

    public static function notCancellable(): array
    {
        return [['shipped'], ['out_for_delivery'], ['delivered'], ['cancelled']];
    }

    #[DataProvider('notCancellable')]
    public function test_an_order_past_dispatch_cannot_be_cancelled_by_the_customer(string $status): void
    {
        $order = $this->order($status);

        $this->asCustomer()->get(route('account.order', $order->number))->assertDontSee('Cancel order');

        $this->asCustomer()->post(route('orders.cancel', $order->number))->assertSessionHasErrors('cancel');
        $this->assertSame($status, $order->refresh()->status);
    }

    public function test_a_customer_cannot_cancel_someone_elses_order(): void
    {
        $order = $this->order('confirmed');
        $other = Customer::create(['client_id' => $this->client->id, 'name' => 'Other', 'email' => 'other@example.test', 'password' => 'password123', 'is_active' => true]);

        $this->asCustomer($other)->post(route('orders.cancel', $order->number))->assertForbidden();
        $this->assertSame('confirmed', $order->refresh()->status);
    }

    public function test_a_stranger_cannot_cancel_an_order(): void
    {
        $order = $this->order('confirmed');

        $this->withSession([Client::SESSION_KEY => 'alpha'])->post(route('orders.cancel', $order->number))->assertForbidden();
        $this->assertSame('confirmed', $order->refresh()->status);
    }

    // ---- Confirmation page ---------------------------------------------------

    public function test_the_guest_who_just_placed_the_order_cancels_it_from_the_confirmation_page(): void
    {
        $order = $this->order('confirmed');
        $guest = $this->withSession([Client::SESSION_KEY => 'alpha', 'last_order_number' => $order->number]);
        $page = route('orders.confirmation', $order->number);

        $guest->get($page)->assertSee('Cancel order')->assertSee(route('orders.cancel', $order->number));

        $guest->from($page)->post(route('orders.cancel', $order->number), ['reason' => 'Duplicate order'])
            ->assertRedirect($page)
            ->assertSessionHas('order_cancelled');
        $this->assertSame(Order::STATUS_CANCELLED, $order->refresh()->status);
        $this->assertNull(last($order->status_history)['by_type'] ?? null);

        $guest->get($page)->assertDontSee('Cancel order');
    }

    public static function templates(): array
    {
        return [['fashion', 'Changed your mind?'], ['bulk', 'Need to cancel?'], ['grocery', 'rounded-2xl bg-white p-0'], ['heritage', 'rounded-2xl bg-white p-0'], ['plumbing', 'rounded-2xl bg-white p-0']];
    }

    #[DataProvider('templates')]
    public function test_every_template_offers_it_on_the_confirmation_and_account_pages(string $template, string $marker): void
    {
        $this->client->storefront->update(['template' => $template, 'slug' => $template]);
        $order = $this->order('confirmed');

        $this->asCustomer()->withSession(['last_order_number' => $order->number])
            ->get(route('orders.confirmation', $order->number))->assertOk()->assertSee(route('orders.cancel', $order->number))->assertSee($marker, false)->assertSee('<dialog', false)->assertDontSee('confirm(', false);
        $this->asCustomer()->get(route('account.order', $order->number))->assertOk()->assertSee(route('orders.cancel', $order->number))->assertSee($marker, false)->assertSee('<dialog', false)->assertDontSee('confirm(', false);
    }

    // ---- Stock ---------------------------------------------------------------

    private function product(array $attributes = []): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Linen Shirt', 'slug' => 'linen-shirt-'.uniqid(),
            'sku' => 'SHIRT-'.uniqid(), 'price' => 1000, 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function line(Order $order, Product $product, int $qty, ?array $options = null): void
    {
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name, 'price' => 1000, 'qty' => $qty, 'total' => 1000 * $qty, 'options' => $options]);
    }

    public function test_cancelling_puts_the_goods_back_in_stock(): void
    {
        $plain = $this->product(['stock' => 4]);
        $variant = $this->product([
            'stock' => 7,
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['S', 'M']]],
            'variants' => [
                ['options' => ['Size' => 'S'], 'price' => 1000, 'stock' => 2],
                ['options' => ['Size' => 'M'], 'price' => 1000, 'stock' => 5],
            ],
        ]);
        $order = $this->order('confirmed');
        $this->line($order, $plain, 3);
        $this->line($order, $variant, 2, ['Size' => 'S']);

        $this->asCustomer()->post(route('orders.cancel', $order->number))->assertSessionHasNoErrors();

        $this->assertSame(7, $plain->refresh()->stock);
        $variant->refresh();
        $this->assertSame(9, $variant->stock);
        $this->assertSame(4, $variant->variants[0]['stock']);
        $this->assertSame(5, $variant->variants[1]['stock']);
    }

    public function test_stock_moves_once_each_way(): void
    {
        $product = $this->product(['stock' => 4]);
        $order = $this->order('processing');
        $this->line($order, $product, 3);

        $this->assertTrue($order->transitionTo(Order::STATUS_CANCELLED, 'Out of area'));
        $this->assertFalse($order->transitionTo(Order::STATUS_CANCELLED, 'Again'));
        $this->assertSame(7, $product->refresh()->stock);

        // A cancelled order is never reopened, so its stock never moves again.
        $this->assertFalse($order->transitionTo('confirmed'));
        $this->assertSame(7, $product->refresh()->stock);

        $this->expectException(\LogicException::class);
        $order->setStatus('confirmed');
    }
}
