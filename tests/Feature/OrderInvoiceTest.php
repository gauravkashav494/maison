<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use App\Support\Invoice;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Order invoices (PDF): who may download one, and what it says.
 */
class OrderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Customer $customer;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        // Invoice numbers depend on the financial year, so the tests run on a fixed day in FY 2026-27.
        $this->travelTo('2026-11-17 10:00:00');

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => [
            'invoice_business_name' => 'Alpha Retail Pvt Ltd',
            'invoice_gstin' => '27AAACA1234A1Z5',
            'invoice_address' => "12 Market Road\nPune 411001",
            'invoice_note' => 'Returns accepted within 7 days.',
        ] + config('client_defaults.checkout')]);
        Cache::forget('settings.all');

        $this->customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha Rao', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);
        $this->order = Order::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'customer_id' => $this->customer->id, 'number' => 'ME-261117-ABCD',
            'email' => 'asha@example.test', 'phone' => '9999999999', 'status' => 'shipped', 'dispatched_at' => now(), 'payment_method' => 'cod', 'payment_status' => 'cod',
            'shipping_name' => 'Asha Rao', 'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai',
            'shipping_state' => 'MH', 'shipping_postal_code' => '400001', 'shipping_country' => 'India', 'shipping_method' => 'Standard',
            'subtotal' => 2000, 'discount' => 0, 'shipping_cost' => 50, 'tax' => 360, 'tax_rate' => 18, 'tax_label' => 'CGST + SGST',
            'prices_include_tax' => false, 'total' => 2410,
        ]);
        OrderItem::create(['order_id' => $this->order->id, 'name' => 'Steel Tiffin', 'sku' => 'TIF-1', 'price' => 1000, 'qty' => 2, 'total' => 2000]);
    }

    private function store()
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha']);
    }

    private function assertPdf($response): void
    {
        $response->assertSuccessful()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString('Invoice-INV-26-27-0001.pdf', $response->headers->get('content-disposition'));
    }

    public function test_the_customer_downloads_their_own_invoice(): void
    {
        $this->assertPdf($this->store()->actingAs($this->customer, 'web')->get('/order/ME-261117-ABCD/invoice'));
    }

    public function test_the_guest_who_just_placed_the_order_downloads_it(): void
    {
        $this->assertPdf($this->withSession([Client::SESSION_KEY => 'alpha', 'last_order_number' => 'ME-261117-ABCD'])->get('/order/ME-261117-ABCD/invoice'));
    }

    public function test_a_signed_link_works_without_a_session(): void
    {
        $this->assertPdf($this->store()->get($this->order->invoiceUrl()));
    }

    public function test_anyone_else_is_refused(): void
    {
        $this->store()->get('/order/ME-261117-ABCD/invoice')->assertForbidden();

        $other = Customer::create(['client_id' => $this->client->id, 'name' => 'Other', 'email' => 'other@example.test', 'password' => 'password123', 'is_active' => true]);
        $this->store()->actingAs($other, 'web')->get('/order/ME-261117-ABCD/invoice')->assertForbidden();

        $this->store()->get('/order/ME-261117-ABCD/invoice?expires=9999999999&signature=forged')->assertForbidden();
    }

    public function test_the_invoice_shows_seller_buyer_items_and_split_tax(): void
    {
        app(ClientContext::class)->resolveForPublic($this->client);

        $html = view('invoices.order', Invoice::for($this->order)->data())->render();

        foreach (['TAX INVOICE', 'INV/26-27/0001', 'Alpha Retail Pvt Ltd', '27AAACA1234A1Z5', 'Pune 411001', 'ME-261117-ABCD', 'Asha Rao', 'Steel Tiffin', 'TIF-1',
            'CGST (9%)', 'SGST (9%)', money(180), money(2410), 'Returns accepted within 7 days.',
            'Rupees Two Thousand Four Hundred Ten Only'] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
    }

    public function test_the_invoice_says_what_the_promo_code_gave(): void
    {
        app(ClientContext::class)->resolveForPublic($this->client);
        $this->order->forceFill(['discount' => 200, 'coupon_code' => 'SAVE10', 'coupon_type' => 'percent', 'coupon_value' => 10])->save();

        $html = view('invoices.order', Invoice::for($this->order)->data())->render();
        $this->assertStringContainsString(e('Discount (SAVE10 · 10% off)'), $html);
    }

    public function test_the_store_name_heads_the_invoice_when_it_differs_from_the_registered_name(): void
    {
        app(ClientContext::class)->resolveForPublic($this->client);
        Setting::set('site', array_replace((array) Setting::get('site'), ['name' => 'Alpha Home']));

        $html = view('invoices.order', Invoice::for($this->order)->data())->render();
        $this->assertStringContainsString('<p class="store-brand">Alpha Home</p>', $html);
        // The registered name still sits with the GSTIN, below the store name.
        $this->assertMatchesRegularExpression('/Alpha Home<\/p>\s*<p class="seller-name">Alpha Retail Pvt Ltd<\/p>/', $html);

        // The same name is not printed twice.
        Setting::set('site', array_replace((array) Setting::get('site'), ['name' => 'alpha retail pvt ltd']));
        $html = view('invoices.order', Invoice::for($this->order)->data())->render();
        $this->assertStringNotContainsString('store-brand">', $html);
    }

    public function test_an_older_order_without_a_kept_store_name_prints_as_before(): void
    {
        app(ClientContext::class)->resolveForPublic($this->client);
        $this->order->update(['seller' => ['name' => 'Alpha Old Name', 'address' => [], 'gstin' => null, 'state' => 'Maharashtra', 'email' => null, 'phone' => null, 'note' => null]]);

        $html = view('invoices.order', Invoice::for($this->order->refresh())->data())->render();
        $this->assertStringNotContainsString('store-brand">', $html);
    }

    public function test_an_invoice_without_hsn_codes_has_no_hsn_column(): void
    {
        app(ClientContext::class)->resolveForPublic($this->client);

        $html = view('invoices.order', Invoice::for($this->order)->data())->render();

        $this->assertStringNotContainsString('>HSN</th>', $html);
    }

    public function test_the_invoice_shows_each_lines_hsn_code(): void
    {
        app(ClientContext::class)->resolveForPublic($this->client);
        OrderItem::create(['order_id' => $this->order->id, 'name' => 'Steel Plate', 'sku' => 'PLT-1', 'hsn_code' => '73239390', 'price' => 0, 'qty' => 1, 'total' => 0]);

        $html = view('invoices.order', Invoice::for($this->order->refresh())->data())->render();

        $this->assertStringContainsString('>HSN</th>', $html);
        $this->assertStringContainsString('73239390', $html);
        // The line without a code shows a dash in that column.
        $this->assertMatchesRegularExpression('/TIF-1<\/td>\s*<td class="muted nowrap">—<\/td>/u', $html);
    }

    public function test_an_order_keeps_the_hsn_code_its_product_had_when_placed(): void
    {
        $order = $this->placeOrderAtCheckout(hsn: '7323');
        $this->assertSame('7323', $order->items()->sole()->hsn_code);

        Product::withoutGlobalScopes()->where('slug', 'steel-tiffin')->update(['hsn_code' => '9999']);

        $this->assertSame('7323', $order->items()->sole()->hsn_code);
    }

    public function test_an_order_placed_before_seller_details_were_kept_reads_todays_settings(): void
    {
        app(ClientContext::class)->resolveForPublic($this->client);
        $this->assertNull($this->order->seller);

        $this->assertSame('Alpha Retail Pvt Ltd', Invoice::for($this->order)->data()['seller']['name']);

        $this->order->update(['seller' => ['name' => 'Alpha Old Name', 'address' => ['Old Address'], 'gstin' => '27OLDGSTIN0000Z', 'state' => 'Maharashtra', 'email' => null, 'phone' => null, 'note' => null]]);
        $html = view('invoices.order', Invoice::for($this->order->refresh())->data())->render();
        foreach (['Alpha Old Name', 'Old Address', '27OLDGSTIN0000Z'] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
        $this->assertStringNotContainsString('Alpha Retail Pvt Ltd', $html);
        $this->assertStringNotContainsString('Returns accepted within 7 days.', $html);
    }

    public function test_a_cancelled_order_keeps_its_invoice_marked_cancelled(): void
    {
        app(ClientContext::class)->resolveForPublic($this->client);
        $render = fn () => view('invoices.order', Invoice::for($this->order->refresh())->data())->render();

        $this->assertStringNotContainsString('CANCELLED', $render());

        $this->travelTo('2026-11-18 09:00:00');
        $this->order->update(['payment_status' => 'paid']);
        $this->order->transitionTo(Order::STATUS_CANCELLED, 'Cancelled by customer: Duplicate order');
        $html = $render();

        foreach (['TAX INVOICE', 'CANCELLED</div>', 'INV/26-27/0001', 'Cancelled on 18 Nov 2026 by the customer', 'Reason: Duplicate order',
            'Amount to be refunded', 'not valid for payment or input tax credit'] as $text) {
            $this->assertStringContainsString(str_ends_with($text, '</div>') ? $text : e($text), $html, $text);
        }
        $this->assertStringContainsString('<div class="watermark">CANCELLED</div>', $html);

        $this->order->update(['payment_status' => 'refunded']);
        $this->assertStringContainsString('Amount refunded', $render());

        // Pay on delivery: nothing was taken, so there is nothing to refund.
        $this->order->update(['payment_status' => 'cod']);
        $this->assertStringNotContainsString('refunded</td>', $render());

        $this->assertPdf($this->store()->actingAs($this->customer, 'web')->get('/order/ME-261117-ABCD/invoice'));
    }

    public function test_amounts_are_written_out_in_indian_words(): void
    {
        $this->assertSame('Rupees One Lakh Twenty-Three Thousand Four Hundred Fifty-Six and Seventy-Eight Paise Only', Invoice::rupeesInWords(123456.78));
        $this->assertSame('Rupees Two Crore Five Only', Invoice::rupeesInWords(20000005));
        $this->assertSame('Rupees Zero Only', Invoice::rupeesInWords(0));
    }

    public function test_the_customer_sees_the_download_link_on_their_order(): void
    {
        $this->store()->actingAs($this->customer, 'web')->get('/account/orders/ME-261117-ABCD')
            ->assertSuccessful()->assertSee('Download invoice')->assertSee('/order/ME-261117-ABCD/invoice', false);
    }

    public function test_the_store_admin_downloads_it_from_the_order_page(): void
    {
        $admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($admin, 'client')->get('/client/orders/'.$this->order->id)->assertSuccessful();
        Filament::setCurrentPanel(Filament::getPanel('client'));

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->callAction('invoice')
            ->assertFileDownloaded('Invoice-INV-26-27-0001.pdf');
    }

    public static function templates(): array
    {
        return array_combine($ids = ['fashion', 'grocery', 'heritage', 'plumbing', 'bulk'], array_map(fn ($id) => [$id], $ids));
    }

    #[DataProvider('templates')]
    public function test_every_template_links_the_invoice_from_confirmation_account_and_tracking(string $template): void
    {
        $store = Storefront::firstOrCreate(['template' => $template], ['name' => $template, 'slug' => $template, 'is_active' => true]);
        $this->client->update(['storefront_id' => $store->id]);
        Order::withoutGlobalScopes()->whereKey($this->order->id)->update(['template' => $template]);
        $link = '/order/ME-261117-ABCD/invoice';

        $this->withSession([Client::SESSION_KEY => 'alpha', 'last_order_number' => 'ME-261117-ABCD'])
            ->get('/order/ME-261117-ABCD/confirmation')->assertSuccessful()->assertSee($link, false);
        $this->flushSession();
        $this->store()->actingAs($this->customer, 'web')
            ->get('/account/orders/ME-261117-ABCD')->assertSuccessful()->assertSee($link, false);
        $this->flushSession();
        $this->store()->post('/track-order', ['number' => 'ME-261117-ABCD', 'email' => 'asha@example.test'])
            ->assertSuccessful()->assertSee($link, false);
    }

    public function test_the_store_admin_views_the_invoice_in_the_browser(): void
    {
        $admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $url = '/client/orders/'.$this->order->id.'/invoice';

        $this->actingAs($admin, 'client')->get('/client/orders/'.$this->order->id)->assertSuccessful()
            ->assertSee('View invoice')->assertSee($url, false);

        $response = $this->actingAs($admin, 'client')->get($url);
        $response->assertSuccessful()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('inline', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_view_invoice_needs_a_store_admin_of_that_store(): void
    {
        $url = '/client/orders/'.$this->order->id.'/invoice';
        $this->get($url)->assertRedirect();

        $plan = Plan::firstOrFail();
        $beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $this->client->storefront_id, 'plan_id' => $plan->id, 'is_active' => true]);
        $other = ClientUser::create([
            'client_id' => $beta->id, 'name' => 'Beta Admin', 'email' => 'admin@beta.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($other, 'client')->get($url)->assertNotFound();
    }

    /** A copy of the order, dispatched on $dispatched (placed then too, unless $placed says otherwise). */
    private function orderDispatchedAt(string $number, string $dispatched, ?Client $client = null, ?string $placed = null): Order
    {
        $order = $this->order->replicate(['invoice_number', 'invoice_fy', 'invoice_seq', 'invoiced_at']);
        $order->forceFill(['number' => $number, 'client_id' => ($client ?? $this->client)->id])->saveQuietly();
        Order::withoutGlobalScopes()->whereKey($order->id)->update(['created_at' => $placed ?? $dispatched, 'dispatched_at' => $dispatched]);

        return Order::withoutGlobalScopes()->findOrFail($order->id);
    }

    public function test_invoice_numbers_run_in_one_series_per_store_and_financial_year(): void
    {
        app(ClientContext::class)->resolveForPublic($this->client);

        $this->assertSame('INV/26-27/0001', $this->order->assignInvoiceNumber());
        $this->assertSame('INV/26-27/0001', $this->order->fresh()->assignInvoiceNumber(), 'A number is given once.');
        $this->assertSame('INV/26-27/0002', $this->orderDispatchedAt('ME-2', '2027-03-31 23:00:00')->assignInvoiceNumber());
        // 1 April starts a new financial year, and a new series. The year is the invoice's
        // (dispatch), not the order's: placed on 31 March, dispatched on 1 April.
        $this->assertSame('INV/27-28/0001', $this->orderDispatchedAt('ME-3', '2027-04-01 09:00:00', placed: '2027-03-31 20:00:00')->assignInvoiceNumber());
        $this->assertSame('INV/25-26/0001', $this->orderDispatchedAt('ME-4', '2026-01-15 09:00:00')->assignInvoiceNumber());

        // Another store keeps its own series and prefix.
        $beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $this->client->storefront_id, 'plan_id' => $this->client->plan_id, 'is_active' => true]);
        Setting::updateOrCreate(['key' => 'client:'.$beta->id.':checkout'], ['value' => ['invoice_prefix' => 'BT'] + config('client_defaults.checkout')]);
        Cache::forget('settings.all');
        app(ClientContext::class)->resolveForPublic($beta);
        $this->assertSame('BT/26-27/0001', $this->orderDispatchedAt('ME-5', '2026-11-17 10:00:00', $beta)->assignInvoiceNumber());
    }

    /** Places an order at checkout as a guest, after the shared order took INV/26-27/0001. */
    private function placeOrderAtCheckout(?string $hsn = null): Order
    {
        $this->order->update(['invoice_number' => 'INV/26-27/0001', 'invoice_fy' => '26-27', 'invoice_seq' => 1]);
        $product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Steel Tiffin',
            'slug' => 'steel-tiffin', 'sku' => 'TIF-1', 'hsn_code' => $hsn, 'price' => 1000, 'stock' => 10, 'is_active' => true,
        ]);
        $checkout = Setting::where('key', 'client:'.$this->client->id.':checkout')->first()->value;
        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => ['shipping_methods' => [['code' => 'standard', 'name' => 'Standard', 'cost' => 0]]] + $checkout]);
        Cache::forget('settings.all');

        $this->store()->postJson('/cart/items', ['product_id' => $product->id, 'options' => [], 'qty' => 1])->assertSuccessful();
        $this->store()->post('/checkout', [
            'email' => 'new@example.test', 'phone' => '9999999999', 'shipping_name' => 'New Buyer',
            'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
            'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'shipping_method' => 'standard', 'payment_method' => 'cod',
        ])->assertSessionHasNoErrors()->assertRedirect(route('otp.verify'));
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertSessionHasNoErrors()->assertRedirect();

        return Order::withoutGlobalScopes()->latest('id')->first();
    }

    public function test_a_new_order_has_no_invoice_until_it_is_dispatched(): void
    {
        $order = $this->placeOrderAtCheckout();
        $this->assertNull($order->invoice_number);
        $this->assertNull($order->invoiced_at);
        $this->assertFalse($order->hasInvoice());

        // No link on the confirmation page, and the invoice itself is not found.
        $session = [Client::SESSION_KEY => 'alpha', 'last_order_number' => $order->number];
        $this->withSession($session)->get('/order/'.$order->number.'/confirmation')
            ->assertSuccessful()->assertDontSee('/order/'.$order->number.'/invoice', false);
        $this->withSession($session)->get('/order/'.$order->number.'/invoice')->assertNotFound();
        $this->withSession([Client::SESSION_KEY => 'alpha'])->get($order->invoiceUrl())->assertNotFound();

        // Dispatched two days later: the next number in the series, dated the day of dispatch.
        $this->travelTo('2026-11-19 15:00:00');
        app(ClientContext::class)->resolveForPublic($this->client);
        $this->assertTrue($order->transitionTo(Order::STATUS_DISPATCHED));
        $order->refresh();
        $this->assertSame('INV/26-27/0002', $order->invoice_number);
        $this->assertSame('2026-11-19', $order->invoiced_at->toDateString());

        $html = view('invoices.order', Invoice::for($order)->data())->render();
        $this->assertMatchesRegularExpression('#Invoice date</td><td class="right">19 Nov 2026#', $html);
        $this->assertMatchesRegularExpression('#Order date</td><td class="right">17 Nov 2026#', $html);

        $this->withSession($session)->get('/order/'.$order->number.'/confirmation')
            ->assertSuccessful()->assertSee('/order/'.$order->number.'/invoice', false);
        $this->withSession($session)->get('/order/'.$order->number.'/invoice')->assertSuccessful()->assertHeader('content-type', 'application/pdf');
    }

    public function test_an_order_cancelled_before_dispatch_never_gets_an_invoice(): void
    {
        $order = $this->placeOrderAtCheckout();
        app(ClientContext::class)->resolveForPublic($this->client);
        $this->assertTrue($order->transitionTo(Order::STATUS_CANCELLED, 'Out of stock'));

        $order->refresh();
        $this->assertNull($order->invoice_number);
        $this->assertFalse($order->hasInvoice());
        $this->expectException(\LogicException::class);
        Invoice::for($order);
    }

    public function test_the_store_admin_sees_the_invoice_actions_only_once_dispatched(): void
    {
        $admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        Order::withoutGlobalScopes()->whereKey($this->order->id)->update(['status' => 'processing', 'dispatched_at' => null]);
        $this->actingAs($admin, 'client')->get('/client/orders/'.$this->order->id)->assertSuccessful();
        $this->actingAs($admin, 'client')->get('/client/orders/'.$this->order->id.'/invoice')->assertNotFound();
        Filament::setCurrentPanel(Filament::getPanel('client'));

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertActionHidden('invoice')->assertActionHidden('viewInvoice');
    }
}
