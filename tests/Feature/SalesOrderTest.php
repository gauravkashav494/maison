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
use App\Support\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

    /** A delivery address in Maharashtra (the seller is in Punjab): IGST. */
    private function address(array $extra = []): array
    {
        return $extra + [
            'shipping_line1' => '4 Marine Drive', 'shipping_city' => 'Mumbai', 'shipping_state' => 'Maharashtra',
            'shipping_postal_code' => '400001', 'shipping_country' => 'India', 'shipping_method' => 'standard',
            'payment_method' => 'cod',
        ];
    }

    /** A sales person's order for themselves, with their own contact details. */
    private function selfOrder(array $extra = []): array
    {
        return $this->address($extra + ['order_for' => 'self', 'shipping_name' => 'Ravi Sales', 'phone' => '9000000001']);
    }

    private function latestOrder(): Order
    {
        return Order::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_checkout_page_asks_who_the_order_is_for_and_the_delivery_address(): void
    {
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->get('/checkout')->assertOk()
            ->assertSee('name="order_for"', false)
            ->assertSee('name="shipping_line1"', false)
            ->assertSee('name="shipping_method"', false);
    }

    /**
     * The "Myself / A customer" switch is an Alpine script written in Blade. A line ending in
     *
     * @js(...) loses its line break (PHP drops the newline after ?>), joining the next line on
     * and breaking the whole script: choosing "A customer" then left the sales person's details
     * in the fields. Each statement of the rendered script must stay on its own line.
     */
    public function test_the_order_for_switch_script_keeps_each_statement_on_its_own_line(): void
    {
        $this->addToCart($this->salesperson);

        $html = $this->as($this->salesperson)->get('/checkout')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/x-data="\{\s*orderFor:/', $html);
        $script = html_entity_decode(Str::between($html, 'x-data="{', '}" class="mt-3"'));

        // A value straight followed, after spaces, by another statement on the same line.
        $this->assertDoesNotMatchRegularExpression("/'(self|customer)' {2,}(this|const|let|box|if)\\b/", $script);
        $this->assertStringContainsString("const from = to === 'self' ? 'customer' : 'self';", $script);
        $this->assertStringContainsString("const self = this.orderFor === 'self';", $script);
    }

    /** The same trap anywhere: no view line may end in @js(...). */
    public function test_no_view_line_ends_in_a_js_directive(): void
    {
        $balanced = '\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\)';
        $found = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            foreach (preg_split('/\R/', $file->getContents()) as $i => $line) {
                if (preg_match('/@js'.$balanced.'\s*$/', $line)) {
                    $found[] = $file->getRelativePathname().':'.($i + 1);
                }
            }
        }

        $this->assertSame([], $found, 'End these lines with ";" or "," so the next line is not joined on.');
    }

    public function test_sales_person_orders_for_themselves_with_a_delivery_address(): void
    {
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', $this->selfOrder(['save_address' => 1]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $order = $this->latestOrder();
        $this->assertSame($this->salesperson->id, (int) $order->customer_id);
        $this->assertSame($this->salesperson->id, (int) $order->salesperson_id);
        $this->assertSame('Ravi Sales', $order->shipping_name);
        $this->assertSame('9000000001', $order->phone);
        $this->assertSame('4 Marine Drive', $order->shipping_line1);
        $this->assertSame('Mumbai', $order->shipping_city);
        $this->assertSame('Maharashtra', $order->shipping_state);
        $this->assertSame('Standard', $order->shipping_method);
        // Delivered like any order: delivery charge, cash-on-delivery fee and a delivery date.
        $this->assertEquals(290, $order->shipping_cost); // 250 delivery + 40 cash-on-delivery fee
        $this->assertNotNull($order->estimated_delivery);
        // GST by where it is delivered: out of the seller's state.
        $this->assertSame('IGST', $order->tax_label);
        $order->setStatus(Order::STATUS_DISPATCHED);
        $this->assertSame('Maharashtra (27)', Invoice::for($order->refresh())->data()['placeOfSupply']);
        $this->assertTrue($order->isSalesOrder());
        // Their own order: the address is saved to their account when asked.
        $this->assertSame(1, $this->salesperson->addresses()->count());
        // Numbered <storefront id>-<client id>-<date>-<code>.
        $this->assertMatchesRegularExpression('/^'.$this->client->storefront_id.'-'.$this->client->id.'-'.now()->format('ymd').'-[A-Z2-9]{4}$/', $order->number);
    }

    public function test_a_sales_order_needs_a_delivery_address(): void
    {
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', ['order_for' => 'self', 'shipping_name' => 'Ravi Sales', 'phone' => '9000000001', 'payment_method' => 'cod'])
            ->assertSessionHasErrors(['shipping_line1', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_method']);

        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_an_older_sales_order_without_an_address_is_still_supplied_in_the_sellers_state(): void
    {
        $this->addToCart($this->salesperson);
        $this->as($this->salesperson)->post('/checkout', $this->selfOrder())->assertSessionHasNoErrors();
        $order = $this->latestOrder();
        $order->forceFill(['shipping_line1' => '', 'shipping_city' => '', 'shipping_state' => '', 'shipping_postal_code' => '', 'shipping_country' => ''])->save();

        $order->setStatus(Order::STATUS_DISPATCHED);

        $this->assertSame('Punjab (03)', Invoice::for($order->refresh())->data()['placeOfSupply']);
    }

    public function test_sales_person_orders_for_a_new_customer(): void
    {
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', $this->address([
            'order_for' => 'customer', 'shipping_name' => 'Meena Kaur', 'phone' => '+91 98765 43210', 'save_address' => 1,
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $order = $this->latestOrder();
        $customer = Customer::where('phone', '9876543210')->firstOrFail();
        $this->assertSame('Meena Kaur', $customer->name);
        $this->assertFalse($customer->isAccount());
        $this->assertFalse($customer->isSalesperson());
        $this->assertSame($customer->id, (int) $order->customer_id);
        $this->assertSame($this->salesperson->id, (int) $order->salesperson_id);
        $this->assertSame('Meena Kaur', $order->shipping_name);
        $this->assertSame('4 Marine Drive', $order->shipping_line1);
        $this->assertEquals(290, $order->shipping_cost); // 250 delivery + 40 cash-on-delivery fee
        // A customer's details are never saved from a sales checkout, nor to the sales person's account.
        $this->assertSame(0, $customer->addresses()->count());
        $this->assertSame(0, $this->salesperson->addresses()->count());
        // Numbered like the customer's own orders: <client id>-<date>-<code>.
        $this->assertMatchesRegularExpression('/^'.$this->client->id.'-'.now()->format('ymd').'-[A-Z2-9]{4}$/', $order->number);
    }

    public function test_sales_person_orders_for_an_existing_customer_by_phone(): void
    {
        $existing = Customer::create([
            'client_id' => $this->client->id, 'name' => 'Meena K', 'email' => 'meena@example.test',
            'phone' => '9876543210', 'password' => 'password123', 'is_active' => true,
        ]);
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', $this->address([
            'order_for' => 'customer', 'shipping_name' => 'Someone Else', 'phone' => '9876543210',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($existing->id, (int) $this->latestOrder()->customer_id);
        // An existing customer is never changed from a sales checkout.
        $this->assertSame('Meena K', $existing->fresh()->name);
        $this->assertSame(2, Customer::count());
    }

    public function test_the_customer_lookup_gives_a_known_customers_saved_address(): void
    {
        $existing = Customer::create([
            'client_id' => $this->client->id, 'name' => 'Meena K', 'email' => 'meena@example.test',
            'phone' => '9876543210', 'password' => 'password123', 'is_active' => true,
        ]);
        $existing->addresses()->create([
            'name' => 'Meena K', 'phone' => '9876543210', 'line1' => '4 Marine Drive', 'city' => 'Mumbai',
            'state' => 'Maharashtra', 'postal_code' => '400001', 'country' => 'India', 'is_default' => true,
        ]);

        $this->as($this->salesperson)->postJson(route('checkout.sales-customer'), ['phone' => '9876543210'])->assertOk()
            ->assertJson(['name' => 'Meena K', 'email' => 'meena@example.test', 'address' => [
                'shipping_line1' => '4 Marine Drive', 'shipping_city' => 'Mumbai', 'shipping_state' => 'Maharashtra', 'shipping_postal_code' => '400001',
            ]]);
        $this->as($this->salesperson)->postJson(route('checkout.sales-customer'), ['phone' => '9123456789'])->assertOk()
            ->assertJson(['name' => null, 'address' => null]);
    }

    public function test_a_customer_order_needs_the_customers_name_and_phone(): void
    {
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', $this->address(['order_for' => 'customer']))
            ->assertSessionHasErrors(['phone', 'shipping_name']);
        $this->as($this->salesperson)->post('/checkout', $this->address(['shipping_name' => 'Ravi Sales', 'phone' => '9000000001']))
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

    public function test_the_order_keeps_the_sellers_details_when_the_settings_change_later(): void
    {
        $setting = Setting::where('key', 'client:'.$this->client->id.':checkout')->firstOrFail();
        $setting->update(['value' => ['invoice_business_name' => 'Alpha Retail Pvt Ltd', 'invoice_gstin' => '03AAACA1234A1Z5',
            'invoice_address' => "12 Mall Road\nLudhiana 141001", 'invoice_note' => 'Thank you.'] + $setting->value]);
        Cache::forget('settings.all');
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', $this->selfOrder())
            ->assertSessionHasNoErrors()->assertRedirect();
        $order = $this->latestOrder();
        $this->assertSame('Alpha Retail Pvt Ltd', $order->seller['name']);
        $this->assertSame(['12 Mall Road', 'Ludhiana 141001'], $order->seller['address']);
        $this->assertSame('03AAACA1234A1Z5', $order->seller['gstin']);
        $this->assertSame('Punjab', $order->seller['state']);

        // The store moves to another state with a new GSTIN and name: the old invoice is unchanged.
        $setting->refresh()->update(['value' => ['invoice_business_name' => 'Alpha Holdings', 'invoice_gstin' => '27AAACA9999A1Z5',
            'invoice_state' => '27', 'invoice_address' => '1 New Street, Pune', 'invoice_note' => 'New note.'] + $setting->value]);
        Cache::forget('settings.all');

        $order->refresh()->setStatus(Order::STATUS_DISPATCHED);
        $data = Invoice::for($order->refresh())->data();
        $this->assertSame('Alpha Retail Pvt Ltd', $data['seller']['name']);
        $this->assertSame('03AAACA1234A1Z5', $data['seller']['gstin']);
        $this->assertSame('Thank you.', $data['seller']['note']);
        $this->assertSame('Maharashtra (27)', $data['placeOfSupply']);
    }

    public function test_a_sales_order_gets_its_invoice_only_when_dispatched(): void
    {
        $this->addToCart($this->salesperson);
        $this->as($this->salesperson)->post('/checkout', $this->selfOrder())
            ->assertSessionHasNoErrors()->assertRedirect();

        $order = $this->latestOrder();
        $this->assertTrue($order->isSalesOrder());
        $this->assertNull($order->invoice_number);
        $this->assertFalse($order->hasInvoice());

        $order->setStatus(Order::STATUS_DISPATCHED);

        $order->refresh();
        $this->assertNotNull($order->invoice_number);
        $this->assertTrue($order->hasInvoice());
    }

    public function test_without_a_gst_state_the_tax_is_plain_gst(): void
    {
        $setting = Setting::where('key', 'client:'.$this->client->id.':checkout')->firstOrFail();
        $setting->update(['value' => ['invoice_state' => '', 'invoice_gstin' => ''] + $setting->value]);
        Cache::forget('settings.all');
        $this->addToCart($this->salesperson);

        $this->as($this->salesperson)->post('/checkout', $this->selfOrder())
            ->assertSessionHasNoErrors()->assertRedirect();

        $order = $this->latestOrder();
        $this->assertSame('GST', $order->tax_label);
    }
}
