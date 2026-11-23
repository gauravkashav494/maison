<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Models\Client;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A coupon's minimum order applies to every type, free shipping included, and keeps applying as the cart changes. */
class CouponMinimumTest extends TestCase
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

        $this->product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Dishwash liquid', 'slug' => 'dishwash',
            'sku' => 'CL-DW-5L', 'price' => 410, 'stock' => 50, 'is_active' => true,
        ]);
        Coupon::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'code' => 'happy', 'type' => 'free_shipping',
            'value' => 0, 'min_subtotal' => 2000, 'is_active' => true,
        ]);

        // One paid delivery method, never free by threshold, so only the coupon could make it free.
        app(ClientContext::class)->resolveForPublic($this->client);
        Setting::set('checkout', ['shipping_methods' => [['code' => 'express', 'name' => 'Express delivery', 'cost' => 150, 'free_over' => null]]] + (Setting::get('checkout') ?? []));
        Setting::set('site', ['free_shipping_threshold' => 0] + (Setting::get('site') ?? []));
    }

    private function onStore(): static
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha']);
    }

    public function test_a_free_shipping_code_is_refused_below_its_minimum(): void
    {
        $this->onStore()->postJson('/cart/items', ['product_id' => $this->product->id, 'qty' => 3])->assertSuccessful();

        $this->onStore()->postJson('/cart/coupon', ['code' => 'happy'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This code needs a minimum subtotal of '.money(2000).'.');

        $cart = $this->onStore()->getJson('/cart/items')->json();
        $this->assertNull($cart['coupon']);
        $this->assertEquals(150, $cart['shipping']);
    }

    public function test_a_free_shipping_code_is_dropped_when_the_cart_falls_below_its_minimum(): void
    {
        $key = $this->onStore()->postJson('/cart/items', ['product_id' => $this->product->id, 'qty' => 5])->assertSuccessful()->json('items.0.key');
        $this->onStore()->postJson('/cart/coupon', ['code' => 'happy'])->assertSuccessful()->assertJsonPath('shipping', 0);

        $cart = $this->onStore()->patchJson('/cart/items/'.$key, ['qty' => 3])->assertSuccessful()->json();

        $this->assertNull($cart['coupon']);
        $this->assertEquals(150, $cart['shipping']);
    }
}
