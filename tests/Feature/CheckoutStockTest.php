<?php

namespace Tests\Feature;

use App\Models\Client;
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
 * Stock is checked again when the order is placed, not only when items go into the cart: two
 * windows (or two shoppers) holding the last units cannot both buy them. The later checkout
 * makes no order; its cart is lowered to what is left and the shopper is told.
 */
class CheckoutStockTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

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
    }

    private function product(string $name, int $stock, array $attributes = []): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->toString(),
            'price' => 500, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    /** Fills a cart in its own window and returns it, so a second window can check out the same cart later. */
    private function cartWith(Product $product, int $qty, array $options = []): array
    {
        $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
            'product_id' => $product->id, 'options' => $options, 'qty' => $qty,
        ])->assertSuccessful();
        $items = session('cart.items');
        $this->flushSession();

        return $items;
    }

    private function checkout(array $cartItems, string $email)
    {
        $customer = Customer::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'email' => $email, 'name' => 'Buyer', 'password' => 'password123', 'is_active' => true]);

        return $this->actingAs($customer, 'web')
            ->withSession([Client::SESSION_KEY => 'alpha', 'cart.items' => $cartItems])
            ->post('/checkout', [
                'email' => $email, 'phone' => '9999999999', 'shipping_name' => 'Buyer',
                'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
                'shipping_postal_code' => '400001', 'shipping_country' => 'India',
                'shipping_method' => 'standard', 'payment_method' => 'cod',
            ]);
    }

    public function test_the_second_window_cannot_buy_stock_the_first_already_sold(): void
    {
        $vase = $this->product('Glass vase', 3);
        $first = $this->cartWith($vase, 3);
        $second = $this->cartWith($vase, 3);

        $this->checkout($first, 'a@alpha.test')->assertRedirect();
        $this->assertSame(0, $vase->fresh()->stock);

        // Nothing orderable left: to the cart, which lists the sold-out line.
        $this->checkout($second, 'b@alpha.test')->assertRedirect(route('cart'));

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $this->assertSame(0, $vase->fresh()->stock);
        // Sold out: the line stays, greyed as out of stock, for the shopper to remove.
        $cart = $this->getJson('/cart/items')->json();
        $this->assertSame([], $cart['items']);
        $this->assertSame([['Glass vase', 'out_of_stock']], array_map(fn ($l) => [$l['name'], $l['reason']], $cart['unavailable']));
        $this->assertSame('Glass vase is out of stock. Please remove it from your cart.', $cart['unavailable_toast']);
    }

    public function test_a_cart_asking_for_more_than_is_left_is_lowered_to_what_is_left(): void
    {
        $vase = $this->product('Glass vase', 5);
        $first = $this->cartWith($vase, 3);
        $second = $this->cartWith($vase, 4);

        $this->checkout($first, 'a@alpha.test')->assertRedirect();
        $this->checkout($second, 'b@alpha.test')->assertRedirect(route('checkout'));

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $this->assertSame(2, $vase->fresh()->stock);
        $this->assertSame(2, array_values(session('cart.items'))[0]['qty']);
        $this->assertSame('Only 2 of Glass vase are left, so your cart has been updated.', $this->getJson('/cart/items')->json('unavailable_notice'));

        // Placing it again with what is left goes through.
        $this->checkout(session('cart.items'), 'c@alpha.test')->assertRedirect();
        $this->assertSame(2, Order::withoutGlobalScopes()->count());
        $this->assertSame(0, $vase->fresh()->stock);
    }

    public function test_a_variant_is_checked_against_its_own_stock(): void
    {
        $shirt = $this->product('Linen shirt', 3, [
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['S', 'M']]],
            'variants' => [
                ['options' => ['Size' => 'S'], 'price' => 900, 'stock' => 2, 'is_active' => true],
                ['options' => ['Size' => 'M'], 'price' => 900, 'stock' => 1, 'is_active' => true],
            ],
        ]);
        $first = $this->cartWith($shirt, 1, ['Size' => 'M']);
        $second = $this->cartWith($shirt, 1, ['Size' => 'M']);

        $this->checkout($first, 'a@alpha.test')->assertRedirect();
        // Nothing orderable left: to the cart, which lists the sold-out line.
        $this->checkout($second, 'b@alpha.test')->assertRedirect(route('cart'));

        $this->assertSame(1, Order::withoutGlobalScopes()->count());
        $this->assertSame(0, $shirt->fresh()->stockFor(['Size' => 'M']));
        $this->assertSame(2, $shirt->fresh()->stockFor(['Size' => 'S']));
        $this->assertSame('Linen shirt (M) is out of stock. Please remove it from your cart.', $this->getJson('/cart/items')->json('unavailable_toast'));
    }
}
