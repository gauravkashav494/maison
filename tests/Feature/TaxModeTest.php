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
 * Prices entered before tax (B2B: tax added on top) or including tax (retail: tax shown as
 * the part of the price that is tax), and each order remembering which applied.
 */
class TaxModeTest extends TestCase
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
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Steel Tiffin',
            'slug' => 'steel-tiffin', 'sku' => 'TIF-1', 'price' => 1000, 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function checkoutSettings(string $mode, float $rate = 18): void
    {
        $defaults = config('client_defaults.checkout');
        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => [
            'tax_mode' => $mode, 'tax_rate' => $rate, 'tax_label' => 'GST',
            // Free delivery so the totals below are only goods and tax.
            'shipping_methods' => [['code' => 'standard', 'name' => 'Standard', 'cost' => 0]],
        ] + $defaults]);
        Cache::forget('settings.all');
    }

    private function addToCart(int $qty = 2)
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
            'product_id' => $this->product->id, 'options' => [], 'qty' => $qty,
        ])->assertSuccessful();
    }

    private function placeOrder(): Order
    {
        $customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Buyer', 'email' => 'buyer@alpha.test', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($customer, 'web')
            ->withSession([Client::SESSION_KEY => 'alpha'])
            ->post('/checkout', [
                'email' => 'buyer@alpha.test', 'phone' => '9999999999', 'shipping_name' => 'Buyer',
                'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
                'shipping_postal_code' => '400001', 'shipping_country' => 'India',
                'shipping_method' => 'standard', 'payment_method' => 'cod',
            ])->assertSessionHasNoErrors()->assertRedirect();

        return Order::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_prices_before_tax_have_the_tax_added_on_top(): void
    {
        $this->checkoutSettings('exclusive');

        $cart = $this->addToCart();

        $this->assertEquals(2000, $cart->json('subtotal'));
        $this->assertEquals(360, $cart->json('tax'));
        $this->assertEquals(2360, $cart->json('total'));
        $this->assertFalse($cart->json('tax_inclusive'));
        $this->assertSame('GST (18%)', $cart->json('tax_line'));
    }

    public function test_prices_including_tax_show_the_tax_inside_the_total(): void
    {
        $this->checkoutSettings('inclusive');

        $cart = $this->addToCart();

        $this->assertEquals(2000, $cart->json('subtotal'));
        $this->assertEquals(305.08, $cart->json('tax'), '18% GST inside ₹2,000.00 is ₹305.08.');
        $this->assertEquals(2000, $cart->json('total'), 'Nothing is added on top.');
        $this->assertTrue($cart->json('tax_inclusive'));
        $this->assertSame('Includes GST (18%)', $cart->json('tax_line'));
    }

    public function test_the_order_remembers_how_tax_applied(): void
    {
        $this->checkoutSettings('exclusive');
        $this->addToCart();
        $order = $this->placeOrder();

        $this->assertFalse($order->prices_include_tax);
        $this->assertSame(18.0, $order->tax_rate);
        $this->assertSame(360.0, $order->tax);
        $this->assertSame(2360.0, $order->total);

        // Switching the store to tax-inclusive later does not change an order already placed.
        $this->checkoutSettings('inclusive');
        $this->assertSame('GST (18%)', $order->fresh()->taxLine());
    }

    public function test_an_inclusive_order_is_labelled_as_including_tax(): void
    {
        $this->checkoutSettings('inclusive');
        $this->addToCart();
        $order = $this->placeOrder();

        $this->assertTrue($order->prices_include_tax);
        $this->assertSame(2000.0, $order->total);
        $this->assertSame('Includes GST (18%)', $order->taxLine());
    }

    public function test_no_rate_reads_as_no_tax_before_tax_and_as_included_inside_the_price(): void
    {
        $this->checkoutSettings('exclusive', 0);
        $cart = $this->addToCart();
        $this->assertSame('₹0.00', $cart->json('tax_formatted'));
        $this->assertEquals(2000, $cart->json('total'));
        $this->assertSame('', price_tax_note());

        $this->checkoutSettings('inclusive', 0);
        $this->assertSame('Included', $this->addToCart()->json('tax_formatted'));
        $this->assertSame('Inclusive of all taxes', price_tax_note());
    }

    public function test_the_cart_lists_the_tax_row_only_when_a_rate_is_set(): void
    {
        $this->addToCart();
        $page = fn () => $this->withSession([Client::SESSION_KEY => 'alpha'])->get('/cart')->assertSuccessful();

        // Inclusive with a rate: the row shows the GST inside the price, and the total says so.
        $this->checkoutSettings('inclusive', 18);
        $page()->assertSee('$store.cart.tax_line', false)->assertSee('Inclusive of all taxes');

        // Inclusive without a rate: no "Includes GST — Included" row with nothing in it.
        $this->checkoutSettings('inclusive', 0);
        $page()->assertDontSee('$store.cart.tax_line', false)->assertSee('Inclusive of all taxes');

        // Before tax: the row when tax is charged, nothing when it is not.
        $this->checkoutSettings('exclusive', 18);
        $page()->assertSee('$store.cart.tax_line', false)->assertDontSee('Inclusive of all taxes');
        $this->checkoutSettings('exclusive', 0);
        $page()->assertDontSee('$store.cart.tax_line', false)->assertDontSee('Inclusive of all taxes');
    }

    public function test_stores_default_to_prices_before_tax(): void
    {
        $this->assertFalse(prices_include_tax());
    }
}
