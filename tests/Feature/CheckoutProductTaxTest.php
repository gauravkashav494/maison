<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use App\Models\Tax;
use App\Support\Invoice;
use App\Support\TaxBreakdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Checkout and invoices charge each product's own tax (Catalogue → Taxes): every line at its
 * product's rate, the order keeping each line's rate and tax, and the invoice listing the tax
 * per rate.
 */
class CheckoutProductTaxTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Product $cleaner;

    private Product $gloves;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $gst18 = Tax::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'GST 18%', 'rate' => 18]);
        $gst5 = Tax::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'GST 5%', 'rate' => 5]);
        $this->cleaner = $this->product('Floor cleaner', 1000, $gst18);
        $this->gloves = $this->product('Cotton gloves', 500, $gst5);
    }

    private function product(string $name, float $price, Tax $tax): Product
    {
        return Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug(),
            'sku' => strtoupper(str($name)->slug()), 'price' => $price, 'stock' => 10, 'is_active' => true, 'tax_id' => $tax->id,
        ]);
    }

    /** The store's own rate is 0: any tax charged below comes from the products' taxes. */
    private function checkoutSettings(string $mode): void
    {
        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => [
            'tax_mode' => $mode, 'tax_rate' => 0,
            'shipping_methods' => [['code' => 'standard', 'name' => 'Standard', 'cost' => 0]],
        ] + config('client_defaults.checkout')]);
        Cache::forget('settings.all');
    }

    private function mergeCheckout(array $values): void
    {
        $setting = Setting::where('key', 'client:'.$this->client->id.':checkout')->firstOrFail();
        $setting->update(['value' => $values + $setting->value]);
        Cache::forget('settings.all');
    }

    private function addToCart(Product $product, int $qty)
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
            'product_id' => $product->id, 'options' => [], 'qty' => $qty,
        ])->assertSuccessful();
    }

    private function placeOrder(string $state = 'MH', string $country = 'India'): Order
    {
        $customer = Customer::withoutGlobalScopes()->firstOrCreate(['client_id' => $this->client->id, 'email' => 'buyer@alpha.test'], ['name' => 'Buyer', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($customer, 'web')
            ->withSession([Client::SESSION_KEY => 'alpha'])
            ->post('/checkout', [
                'email' => 'buyer@alpha.test', 'phone' => '9999999999', 'shipping_name' => 'Buyer',
                'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => $state,
                'shipping_postal_code' => '400001', 'shipping_country' => $country,
                'shipping_method' => 'standard', 'payment_method' => 'cod',
            ])->assertSessionHasNoErrors()->assertRedirect();

        return Order::withoutGlobalScopes()->with('items')->latest('id')->firstOrFail();
    }

    public function test_each_line_is_taxed_at_its_products_rate(): void
    {
        $this->checkoutSettings('exclusive');

        $this->addToCart($this->cleaner, 2);
        $cart = $this->addToCart($this->gloves, 1);

        // 2 × ₹1,000 at 18% = ₹360; 1 × ₹500 at 5% = ₹25.
        $this->assertEquals(2500, $cart->json('subtotal'));
        $this->assertEquals(385, $cart->json('tax'));
        $this->assertEquals(2885, $cart->json('total'));
        $this->assertNull($cart->json('tax_rate')); // the lines differ
        $this->assertSame('GST', $cart->json('tax_line'));
        $this->assertSame(['GST (5%)', 'GST (18%)'], array_column($cart->json('tax_rows'), 'line'));
        $this->assertEquals([25, 360], array_column($cart->json('tax_rows'), 'amount'));
    }

    public function test_one_rate_across_the_cart_is_named_in_the_label(): void
    {
        $this->checkoutSettings('inclusive');

        $cart = $this->addToCart($this->cleaner, 2);

        $this->assertEquals(305.08, $cart->json('tax')); // the 18% inside ₹2,000
        $this->assertEquals(2000, $cart->json('total'));
        $this->assertSame('Includes GST (18%)', $cart->json('tax_line'));
    }

    public function test_the_order_keeps_each_lines_rate_and_tax(): void
    {
        $this->checkoutSettings('exclusive');
        $this->addToCart($this->cleaner, 2);
        $this->addToCart($this->gloves, 1);

        $order = $this->placeOrder();

        $this->assertSame(385.0, $order->tax);
        $this->assertNull($order->tax_rate);
        $this->assertSame(2885.0, $order->total);
        $lines = $order->items->keyBy('name');
        $this->assertSame([18.0, 360.0], [$lines['Floor cleaner']->tax_rate, $lines['Floor cleaner']->tax]);
        $this->assertSame([5.0, 25.0], [$lines['Cotton gloves']->tax_rate, $lines['Cotton gloves']->tax]);

        // A later change to a product's tax does not change the order.
        Tax::withoutGlobalScopes()->where('name', 'GST 5%')->update(['rate' => 12]);
        $this->assertEquals([25, 360], array_column($order->fresh()->taxRows(), 'amount'));
    }

    public function test_the_invoice_lists_the_tax_per_rate_split_into_cgst_and_sgst(): void
    {
        // Seller in Maharashtra, shipping to MH: within the state.
        $this->checkoutSettings('exclusive');
        $this->mergeCheckout(['invoice_state' => '27']);
        $this->addToCart($this->cleaner, 2);
        $this->addToCart($this->gloves, 1);
        $order = $this->placeOrder();

        $rows = $order->taxRows();
        $this->assertSame(['CGST', 'SGST', 'CGST', 'SGST'], array_column($rows, 'label'));
        $this->assertEquals([2.5, 2.5, 9, 9], array_column($rows, 'rate'));
        $this->assertEquals([12.5, 12.5, 180, 180], array_column($rows, 'amount'));
        $this->assertEquals([500, 500, 2000, 2000], array_column($rows, 'taxable'));

        app(ClientContext::class)->resolveForPublic($this->client);
        $html = view('invoices.order', Invoice::for($order)->data())->render();
        foreach (['CGST (2.5%)', 'SGST (2.5%)', 'CGST (9%)', 'SGST (9%)', money(12.5), money(180), money(2885), '18%', '5%'] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
    }

    public function test_a_discount_is_shared_across_the_lines_by_value(): void
    {
        // ₹300 off ₹2,500: ₹240 comes off the ₹2,000 line and ₹60 off the ₹500 line.
        $b = TaxBreakdown::for([['amount' => 2000, 'rate' => 18], ['amount' => 500, 'rate' => 5]], 300, false);

        $this->assertEquals([['taxable' => 1760, 'tax' => 316.8], ['taxable' => 440, 'tax' => 22]], $b['lines']);
        $this->assertEquals(338.8, $b['tax']);
        $this->assertNull($b['rate']);

        // Including tax: the tax is the part of what is paid, and the taxable value is the rest.
        $b = TaxBreakdown::for([['amount' => 1180, 'rate' => 18]], 0, true);
        $this->assertEquals([['taxable' => 1000, 'tax' => 180]], $b['lines']);
        $this->assertSame(18.0, $b['rate']);
    }

    public function test_the_product_page_note_uses_the_products_rate(): void
    {
        $this->checkoutSettings('exclusive');
        app(ClientContext::class)->resolveForPublic($this->client);

        $this->assertSame('+ 5% GST, added at checkout', price_tax_note($this->gloves->effectiveTaxRate()));
        $this->assertSame('', price_tax_note()); // the store's own rate is 0
        $this->assertTrue(shows_tax_row()); // its products are taxed
    }

    public function test_gst_is_split_within_the_sellers_state_and_igst_to_another(): void
    {
        $this->checkoutSettings('exclusive');
        // Seller in Punjab: from the GSTIN until a state is chosen.
        $this->mergeCheckout(['invoice_gstin' => '03AAAAA0000A1Z5']);

        $place = function (string $state, string $country = 'India') {
            $this->addToCart($this->cleaner, 1);
            $order = $this->placeOrder($state, $country);

            return $order;
        };

        $within = $place('punjab');
        $this->assertSame('CGST + SGST', $within->tax_label);
        $this->assertSame(['CGST (9%)', 'SGST (9%)'], array_map(fn ($r) => tax_line($r['label'], $r['rate'], false), $within->taxRows()));
        $this->assertEquals([90, 90], array_column($within->taxRows(), 'amount'));
        $this->assertSame('Punjab (03)', Invoice::for($within)->data()['placeOfSupply']);

        $other = $place('MH');
        $this->assertSame('IGST', $other->tax_label);
        $this->assertSame('IGST (18%)', $other->taxLine());
        $this->assertSame('Maharashtra (27)', Invoice::for($other)->data()['placeOfSupply']);

        // (An address outside India, or a state we cannot read, is refused at checkout:
        // StorefrontValidationTest.)

        // A state chosen in the settings wins over the GSTIN's.
        $this->mergeCheckout(['invoice_state' => '27']);
        $this->assertSame('CGST + SGST', $place('Maharashtra')->tax_label);
    }

    public function test_without_a_seller_state_the_order_says_gst(): void
    {
        $this->checkoutSettings('exclusive');
        $this->addToCart($this->cleaner, 1);
        $this->assertSame('GST', $this->placeOrder('MH')->tax_label);
    }

    public function test_the_checkout_page_names_the_tax_from_the_state_typed(): void
    {
        $this->checkoutSettings('exclusive');
        $this->addToCart($this->cleaner, 1);
        $page = fn () => $this->actingAs(Customer::withoutGlobalScopes()->firstOrCreate(['client_id' => $this->client->id, 'email' => 'buyer@alpha.test'], ['name' => 'Buyer', 'password' => 'password123', 'is_active' => true]), 'web')
            ->withSession([Client::SESSION_KEY => 'alpha'])->get('/checkout')->assertOk();

        // No seller state: the page has nothing to compare with, so it stays GST.
        $page()->assertSee('x-for="row in taxRows"', false)->assertSee('\\u0022seller\\u0022:null', false);

        // Seller in Punjab: the page gets its code and the states it can read, e.g. "PB".
        $this->mergeCheckout(['invoice_state' => '03']);
        $page()->assertSee('\\u0022seller\\u0022:\\u002203\\u0022', false)->assertSee('\\u0022pb\\u0022:\\u002203\\u0022', false);
    }
}

