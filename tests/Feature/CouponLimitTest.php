<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A coupon's limits are checked again when the order is placed, with the coupon locked: two
 * checkouts cannot both take its last use, and one customer (by account or phone) cannot use it
 * more often than "uses per customer" allows. A refused order is not made; the coupon comes off
 * the cart and the shopper is told before placing the order again.
 */
class CouponLimitTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => [
            'shipping_methods' => [['code' => 'standard', 'name' => 'Standard', 'cost' => 0]],
        ] + config('client_defaults.checkout')]);
        Cache::forget('settings.all');

        $this->product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Glass vase', 'slug' => 'glass-vase',
            'price' => 1000, 'stock' => 50, 'is_active' => true,
        ]);
    }

    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->client->id, 'template' => 'fashion', 'code' => 'SAVE10', 'type' => 'percent',
            'value' => 10, 'min_subtotal' => 0, 'is_active' => true,
        ]);
    }

    private function customer(string $email, string $phone = '9999999999'): Customer
    {
        return Customer::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'email' => $email, 'phone' => $phone, 'name' => 'Buyer', 'password' => 'password123', 'is_active' => true]);
    }

    /** Fills a cart with the coupon applied in its own window and returns its session, for a checkout later. */
    private function cartWithCoupon(): array
    {
        $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', ['product_id' => $this->product->id, 'qty' => 1])->assertSuccessful();
        $cart = ['cart.items' => session('cart.items'), 'cart.coupon' => 'SAVE10'];
        $this->flushSession();

        return $cart;
    }

    private function checkout(array $cart, Customer $customer, ?string $phone = null)
    {
        return $this->actingAs($customer, 'web')
            ->withSession([Client::SESSION_KEY => 'alpha'] + $cart)
            ->post('/checkout', [
                'email' => $customer->email, 'phone' => $phone ?? $customer->phone, 'shipping_name' => 'Buyer',
                'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
                'shipping_postal_code' => '400001', 'shipping_country' => 'India',
                'shipping_method' => 'standard', 'payment_method' => 'cod',
            ]);
    }

    public function test_two_checkouts_cannot_both_take_the_last_use(): void
    {
        $coupon = $this->coupon(['usage_limit' => 1]);
        $first = $this->cartWithCoupon();
        $second = $this->cartWithCoupon();

        $this->checkout($first, $this->customer('a@alpha.test', '9000000001'))->assertRedirect();
        $this->assertSame(1, $coupon->fresh()->used_count);

        // The second window still shows the code, but its last use has gone: no order.
        $this->checkout($second, $this->customer('b@alpha.test', '9000000002'))->assertRedirect(route('checkout'));

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $this->assertSame(1, $coupon->fresh()->used_count);
        $this->assertNull(session('cart.coupon'));
        $this->assertSame('The code SAVE10 is no longer available, so it has been removed and your total updated.', $this->getJson('/cart/items')->json('unavailable_notice'));

        // Placed again after seeing the new total: it goes through at full price.
        $this->checkout(['cart.items' => session('cart.items')], Customer::withoutGlobalScopes()->where('email', 'b@alpha.test')->first())->assertRedirect();
        $order = Order::withoutGlobalScopes()->latest('id')->first();
        $this->assertNull($order->coupon_code);
        $this->assertEquals(0, $order->discount);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_one_customer_cannot_use_a_code_more_often_than_allowed(): void
    {
        $coupon = $this->coupon(['per_customer_limit' => 1]);
        $buyer = $this->customer('a@alpha.test', '9000000001');

        $this->checkout($this->cartWithCoupon(), $buyer)->assertRedirect();
        $this->assertSame('SAVE10', Order::withoutGlobalScopes()->first()->coupon_code);

        $this->checkout($this->cartWithCoupon(), $buyer)->assertRedirect(route('checkout'));
        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $this->assertNull(session('cart.coupon'));
        $this->assertSame('You have already used the code SAVE10, so it has been removed and your total updated.', $this->getJson('/cart/items')->json('unavailable_notice'));

        // Someone else can still use it.
        $this->checkout($this->cartWithCoupon(), $this->customer('b@alpha.test', '9000000002'))->assertRedirect();
        $this->assertSame(2, Order::withoutGlobalScopes()->where('coupon_code', 'SAVE10')->count());
        $this->assertSame(2, $coupon->fresh()->used_count);
    }

    public function test_a_second_account_with_the_same_phone_counts_as_the_same_customer(): void
    {
        $this->coupon(['per_customer_limit' => 1]);

        $this->checkout($this->cartWithCoupon(), $this->customer('a@alpha.test', '9000000001'))->assertRedirect();
        $this->checkout($this->cartWithCoupon(), $this->customer('other@alpha.test', '9000000002'), phone: '9000000001')
            ->assertRedirect(route('checkout'));

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
    }

    public function test_a_cancelled_order_gives_the_customer_their_use_back(): void
    {
        $this->coupon(['per_customer_limit' => 1]);
        $buyer = $this->customer('a@alpha.test', '9000000001');

        $this->checkout($this->cartWithCoupon(), $buyer)->assertRedirect();
        Order::withoutGlobalScopes()->first()->update(['status' => Order::STATUS_CANCELLED]);

        $this->checkout($this->cartWithCoupon(), $buyer)->assertRedirect();
        $this->assertSame(2, Order::withoutGlobalScopes()->where('coupon_code', 'SAVE10')->count());
    }

    public function test_a_signed_in_customer_is_told_at_once_when_their_uses_are_spent(): void
    {
        $this->coupon(['per_customer_limit' => 1]);
        $buyer = $this->customer('a@alpha.test', '9000000001');
        $this->checkout($this->cartWithCoupon(), $buyer)->assertRedirect();

        $this->actingAs($buyer, 'web')->withSession([Client::SESSION_KEY => 'alpha'])
            ->postJson('/cart/items', ['product_id' => $this->product->id, 'qty' => 1])->assertSuccessful();
        $this->postJson('/cart/coupon', ['code' => 'save10'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already used this code.');
        $this->assertNull(session('cart.coupon'));
    }

    public function test_a_code_another_store_has_used_up_is_not_counted_here(): void
    {
        $this->coupon(['per_customer_limit' => 1]);
        // An order with the same code and phone in another client's store.
        $other = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $this->client->storefront_id, 'plan_id' => $this->client->plan_id, 'is_active' => true]);
        Order::withoutGlobalScopes()->create([
            'client_id' => $other->id, 'template' => 'fashion', 'number' => 'ME-OTHER-1', 'status' => 'confirmed', 'phone' => '9000000001',
            'shipping_name' => 'Buyer', 'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
            'shipping_postal_code' => '400001', 'shipping_country' => 'India', 'shipping_method' => 'Standard',
            'payment_method' => 'cod', 'payment_status' => 'cod', 'subtotal' => 1000, 'total' => 900, 'coupon_code' => 'SAVE10',
        ]);

        $this->checkout($this->cartWithCoupon(), $this->customer('a@alpha.test', '9000000001'))->assertRedirect();

        $order = Order::withoutGlobalScopes()->where('client_id', $this->client->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('SAVE10', $order->coupon_code);
    }
}
