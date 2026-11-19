<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Storefront;
use App\Support\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A sales person (Customers → Sales person) checks out with no delivery address and no delivery
 * charge, for themselves or for a customer they serve; no address goes on the order (for now) and
 * the order remembers which sales person placed it.
 */
class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Product $product;

    private Customer $salesperson;

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
        Store::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Main shop',
            'address' => '12 Mall Road', 'city' => 'Ludhiana', 'state' => 'Punjab', 'postal_code' => '141001',
            'country' => 'India', 'is_active' => true, 'sort_order' => 0,
        ]);
        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => [
            'shipping_methods' => [['code' => 'standard', 'name' => 'Standard', 'cost' => 250]],
            'cod_fee' => 40,
            'invoice_state' => '03',
        ] + config('client_defaults.checkout')]);
        Cache::forget('settings.all');

        $this->salesperson = Customer::create([
            'client_id' => $this->client->id, 'name' => 'Ravi Sales', 'email' => 'ravi@alpha.test',
            'phone' => '9000000001', 'password' => 'password123', 'is_active' => true, 'is_salesperson' => true,
        ]);
    }

    private function as(Customer $customer): static
    {
        return $this->actingAs($customer, 'web')->withSession([Client::SESSION_KEY => 'alpha']);
    }

    private function addToCart(Customer $customer): void
    {
        $this->as($customer)->postJson('/cart/items', ['product_id' => $this->product->id, 'options' => [], 'qty' => 2])->assertSuccessful();
    }

    private function latestOrder(): Order
    {
        return Order::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_checkout_page_asks_who_the_order_is_for_instead_of_an_address(): void
    {
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->get('/checkout')->assertOk()
            ->assertSee('Who is this order for?')
            ->assertSee('name="order_for"', false)
            ->assertDontSee('name="shipping_line1"', false)
            ->assertDontSee('name="shipping_method"', false);
    }

    public function test_sales_person_orders_for_themselves_with_no_address_and_no_delivery_charge(): void
    {
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', ['order_for' => 'self', 'payment_method' => 'cod'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $order = $this->latestOrder();
        $this->assertSame($this->salesperson->id, (int) $order->customer_id);
        $this->assertSame($this->salesperson->id, (int) $order->salesperson_id);
        $this->assertSame('Ravi Sales', $order->shipping_name);
        $this->assertSame('9000000001', $order->phone);
        $this->assertSame('', $order->shipping_line1);
        $this->assertSame('', $order->shipping_city);
        $this->assertSame('', $order->shipping_state);
        $this->assertSame('', $order->shipping_postal_code);
        $this->assertSame('', $order->shipping_country);
        $this->assertSame([$order->shipping_name], $order->shippingAddressLines());
        $this->assertSame('No delivery (sales order)', $order->shipping_method);
        // No delivery charge and no cash-on-delivery fee.
        $this->assertEquals(0, $order->shipping_cost);
        $this->assertEquals(2000, $order->total);
        $this->assertNull($order->estimated_delivery);
        // Sold at the counter, within the seller's own state.
        $this->assertSame('CGST + SGST', $order->tax_label);
        $this->assertSame('Punjab (03)', \App\Support\Invoice::for($order)->data()['placeOfSupply']);
        $this->assertTrue($order->isSalesOrder());
        $this->assertSame(0, $this->salesperson->addresses()->count());
    }

    public function test_sales_person_orders_for_a_new_customer(): void
    {
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', [
            'order_for' => 'customer', 'shipping_name' => 'Meena Kaur', 'phone' => '+91 98765 43210', 'payment_method' => 'cod',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order = $this->latestOrder();
        $customer = Customer::where('phone', '9876543210')->firstOrFail();
        $this->assertSame('Meena Kaur', $customer->name);
        $this->assertFalse($customer->isAccount());
        $this->assertFalse($customer->isSalesperson());
        $this->assertSame($customer->id, (int) $order->customer_id);
        $this->assertSame($this->salesperson->id, (int) $order->salesperson_id);
        $this->assertSame('Meena Kaur', $order->shipping_name);
        $this->assertSame('', $order->shipping_line1);
        $this->assertEquals(0, $order->shipping_cost);
    }

    public function test_sales_person_orders_for_an_existing_customer_by_phone(): void
    {
        $existing = Customer::create([
            'client_id' => $this->client->id, 'name' => 'Meena K', 'email' => 'meena@example.test',
            'phone' => '9876543210', 'password' => 'password123', 'is_active' => true,
        ]);
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', [
            'order_for' => 'customer', 'shipping_name' => 'Someone Else', 'phone' => '9876543210', 'payment_method' => 'cod',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($existing->id, (int) $this->latestOrder()->customer_id);
        // An existing customer is never changed from a sales checkout.
        $this->assertSame('Meena K', $existing->fresh()->name);
        $this->assertSame(2, Customer::count());
    }

    public function test_a_customer_order_needs_the_customers_name_and_phone(): void
    {
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', ['order_for' => 'customer', 'payment_method' => 'cod'])
            ->assertSessionHasErrors(['phone', 'shipping_name']);
        $this->as($this->salesperson)->post('/checkout', ['payment_method' => 'cod'])
            ->assertSessionHasErrors(['order_for']);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_a_normal_customer_still_needs_a_delivery_address(): void
    {
        $customer = Customer::create([
            'client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test',
            'phone' => '9000000002', 'password' => 'password123', 'is_active' => true,
        ]);
        $this->addToCart($customer);

        $this->as($customer)->post('/checkout', ['order_for' => 'self', 'phone' => '9000000002', 'shipping_name' => 'Asha', 'payment_method' => 'cod'])
            ->assertSessionHasErrors(['shipping_line1', 'shipping_city', 'shipping_method']);

        $this->as($customer)->get('/checkout')->assertOk()->assertDontSee('name="order_for"', false);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_a_shopper_cannot_make_themselves_a_sales_person(): void
    {
        $customer = Customer::create([
            'client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test',
            'phone' => '9000000002', 'password' => 'password123', 'is_active' => true,
        ]);

        $this->as($customer)->put('/account', ['name' => 'Asha', 'email' => 'asha@example.test', 'phone' => '9000000002', 'is_salesperson' => 1]);
        $this->as($customer)->patch('/account', ['name' => 'Asha', 'email' => 'asha@example.test', 'phone' => '9000000002', 'is_salesperson' => 1]);

        $this->assertFalse($customer->fresh()->isSalesperson());
    }

    public function test_without_a_gst_state_the_tax_is_plain_gst(): void
    {
        $setting = Setting::where('key', 'client:'.$this->client->id.':checkout')->firstOrFail();
        $setting->update(['value' => ['invoice_state' => '', 'invoice_gstin' => ''] + $setting->value]);
        Cache::forget('settings.all');
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', ['order_for' => 'self', 'payment_method' => 'cod'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $order = $this->latestOrder();
        $this->assertSame('GST', $order->tax_label);
        $this->assertSame(SalesOrder::shippingMethod()['name'], $order->shipping_method);
    }
}
