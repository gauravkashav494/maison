<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Pages\BulkOrderSettings;
use App\Http\Requests\Storefront\StorefrontRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use App\Services\Cart;
use App\Templates\Bulk\OrderLines;
use App\Templates\TemplateManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Bulk Order template: an order sheet, a quick-order pad and repeat orders.
 *
 * It reuses the shared catalogue, cart and checkout, so these tests check what is new — that
 * every variant becomes a line with its own code, that a line posts exactly what the shared
 * cart accepts, that the pad can find every code, and that a buyer's past order comes back
 * ready to repeat.
 */
class BulkTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        Storefront::syncWithTemplates();
        $store = Storefront::where('template', 'bulk')->firstOrFail();

        $this->client = Client::create([
            'name' => 'Trade Supplies Demo', 'subdomain' => 'trade',
            'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true,
        ]);

        app(ClientContext::class)->resolveForPublic($this->client);
        $store->templateObject()->install();
    }

    private function visit(string $path)
    {
        return $this->get($this->client->publicUrl().$path);
    }

    private function product(string $sku): Product
    {
        return Product::withoutGlobalScopes()->where('client_id', $this->client->id)->where('sku', $sku)->firstOrFail();
    }

    private function order(array $payload)
    {
        return $this->withSession([Client::SESSION_KEY => 'trade'])->postJson('/cart/items', $payload);
    }

    public function test_the_template_is_registered_and_installs(): void
    {
        $store = Storefront::where('template', 'bulk')->firstOrFail();

        $this->assertSame('Bulk Order', $store->templateObject()->name());
        $this->assertFalse($store->supportsServices(), 'It is a catalogue store, so cart and checkout apply.');
        $this->assertTrue($store->templateObject()->isInstalled());
    }

    public function test_every_active_variant_becomes_a_line_with_its_own_code_price_and_stock(): void
    {
        $lines = collect(OrderLines::for($this->product('PK-CT')))->keyBy('sku');

        $this->assertSame(['PK-CT-250', 'PK-CT-500', 'PK-CT-750', 'PK-CT-1000'], $lines->keys()->all());
        $this->assertSame(265.0, $lines['PK-CT-500']['price']);
        $this->assertSame(410, $lines['PK-CT-500']['stock']);
        $this->assertSame(['Size' => '500 ml'], $lines['PK-CT-500']['options']);
        $this->assertSame('500 ml', $lines['PK-CT-500']['label']);
    }

    public function test_a_line_is_capped_by_the_cart_limit_and_stock(): void
    {
        $gloves = collect(OrderLines::for($this->product('GL-NT')))->keyBy('sku');

        $this->assertSame(OrderLines::MAX_PER_LINE, $gloves['GL-NT-M']['max']);
        $this->assertSame(0, $gloves['GL-NT-XL']['max'], 'An out-of-stock variant cannot be ordered.');

        $single = OrderLines::for($this->product('CL-FC-5L'));
        $this->assertCount(1, $single);
        $this->assertSame('can', $single[0]['unit']);
        $this->assertSame(['Size' => 'can'], $single[0]['options'], 'The single pack size is filled in for the cart.');
    }

    public function test_a_line_carries_its_list_price_and_discount_for_the_order_form(): void
    {
        $offer = OrderLines::for($this->product('CL-FC-5L'))[0];
        $this->assertSame(600.0, $offer['list_price']);
        $this->assertSame(540.0, $offer['price']);
        $this->assertSame(10.0, $offer['discount_percent']);

        $plain = OrderLines::for($this->product('CL-HS-5L'))[0];
        $this->assertSame($plain['price'], $plain['list_price']);
        $this->assertNull($plain['discount_percent'], 'No offer, so the form shows N/A.');

        $variant = collect(OrderLines::for($this->product('GL-NT')))->firstWhere('sku', 'GL-NT-M');
        $this->assertNull($variant['discount_percent'], 'A variant has no list price of its own.');
    }

    public function test_the_per_line_limit_matches_what_the_shared_cart_accepts(): void
    {
        // If the cart's limit ever changes, the sheet and pad must change with it.
        $line = collect(OrderLines::for($this->product('GL-NT')))->firstWhere('sku', 'GL-NT-M');
        $payload = ['product_id' => $line['product_id'], 'options' => $line['options']];

        $this->order($payload + ['qty' => OrderLines::MAX_PER_LINE])->assertSuccessful();
        $this->order($payload + ['qty' => OrderLines::MAX_PER_LINE + 1])->assertUnprocessable();
    }

    public function test_a_line_posts_exactly_what_the_shared_cart_accepts(): void
    {
        foreach (['PK-CT-500' => 'PK-CT', 'CL-FC-5L' => 'CL-FC-5L'] as $code => $product) {
            $line = collect(OrderLines::for($this->product($product)))->firstWhere('sku', $code);
            $this->order(['product_id' => $line['product_id'], 'options' => $line['options'], 'qty' => 3])->assertSuccessful();
        }

        $items = collect($this->withSession([Client::SESSION_KEY => 'trade'])->getJson('/cart/items')->json('items'))->keyBy('variant_sku');
        $this->assertSame(3, $items['PK-CT-500']['qty']);
        $this->assertSame(265 * 3, $items['PK-CT-500']['line_total']);
        $this->assertSame(3, $items['CL-FC-5L']['qty']);
    }

    public function test_the_quick_order_index_finds_every_code_case_insensitively(): void
    {
        $products = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->get();
        $index = OrderLines::index(OrderLines::forMany($products));

        $this->assertCount(1, $index['codes']['gl-nt-m']);
        $this->assertSame('GL-NT-M', $index['lines'][$index['codes']['gl-nt-m'][0]]['sku']);
        $this->assertArrayHasKey('of-a4-75', $index['codes']);
        // The variant products' own codes are not lines: only their variants are.
        $this->assertArrayNotHasKey('pk-ct', $index['codes']);
    }

    public function test_the_example_in_the_empty_pad_uses_real_codes(): void
    {
        $example = app(TemplateManager::class)->get('bulk')->defaults()['site']['pad_example'];
        $products = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->get();
        $codes = OrderLines::index(OrderLines::forMany($products))['codes'];

        foreach (preg_split('/\R/', $example) as $line) {
            $code = mb_strtolower(preg_split('/[\s,;]+/', trim($line))[0]);
            $this->assertArrayHasKey($code, $codes, "The pad example's {$code} must be orderable.");
        }
    }

    public function test_the_home_page_is_a_storefront_with_the_bulk_order_form(): void
    {
        $this->visit('/')
            ->assertSuccessful()
            ->assertDontSee('Shop by category')       // categories live in the header bar only
            ->assertSee('Cleaning Chemicals')         // …which lists them
            ->assertDontSee('Most ordered')           // no product lists: the order form is the page
            ->assertDontSee('New in')
            ->assertDontSee('orderSheet(', false)
            ->assertDontSee('tradeCard(', false)
            ->assertSee('id="bulk-order"', false)
            ->assertSee('itemsForm(', false)          // the invoice-style order form
            ->assertSee('quickOrder(', false)         // the paste-a-list tab
            ->assertSee('Download sample CSV')
            ->assertSee('GL-NT-L')                    // every variant's code is searchable in the form
            ->assertSee('Sign in to see your recent orders')
            // No delivery or invoice promises the store has not made itself.
            ->assertDontSee('Same-day dispatch')
            ->assertDontSee('GST invoice')
            // The store's own cookie banner, on the shared consent store.
            ->assertSee('Cookies on Trade Supplies Demo')
            ->assertSee('$store.cookies.acceptAll()', false)
            ->assertDontSee('Cookies, briefly.');
    }

    public function test_the_header_link_for_the_open_page_is_marked_current(): void
    {
        $bar = fn (string $path) => (string) str($this->visit($path)->assertSuccessful()->getContent())
            ->after('class="bk-catbar"')->before('</nav>')->squish()->replace(' >', '>')->replace('  ', ' ');

        $this->assertStringContainsString('href="/track-order" class="is-on" aria-current="page"', $bar('/track-order'));
        $this->assertStringContainsString('href="/contact" class="is-on" aria-current="page"', $bar('/contact'));
        $this->assertStringNotContainsString('href="/track-order" class="is-on"', $bar('/contact'));
        $this->assertStringNotContainsString('aria-current="page"', $bar('/'));
    }

    public function test_the_store_uses_its_own_brand_colour(): void
    {
        Setting::set('bulk_site', ['brand_color' => '#7a1f5c'] + (Setting::get('bulk_site') ?? []));

        $this->visit('/')->assertSee('--bk-brand: #7a1f5c', false);

        // Anything but a plain hex colour is ignored, so the setting cannot inject CSS.
        Setting::set('bulk_site', ['brand_color' => 'red;}</style><script>'] + Setting::get('bulk_site'));
        $this->visit('/')->assertSee('--bk-brand: #0f4c45', false)->assertDontSee('red;}</style>', false);
    }

    public function test_the_catalogue_lists_every_size_as_a_row(): void
    {
        $this->visit('/shop')
            ->assertSuccessful()
            ->assertSee('All products')
            ->assertSee('orderSheet(', false)        // a list, like every category
            ->assertDontSee('tradeCard(', false)
            ->assertSee('bk-group', false)           // a heading row per category
            ->assertSee('Round food container with lid, clear')
            ->assertSee('PK-CT-250')                 // each size on its own row
            ->assertSee('GL-NT-XL');

        // A category is a list to order down: a row per size, a quantity box each, one button to add them all.
        $this->visit('/shop/cleaning')
            ->assertSuccessful()
            ->assertSee('orderSheet(', false)
            ->assertDontSee('tradeCard(', false)
            ->assertSee('Floor cleaner concentrate')
            ->assertSee('CL-FC-5L')
            ->assertSee('Add all to order')
            ->assertDontSee('A4 copier paper');
        $this->visit('/shop/hygiene')->assertSuccessful()->assertSee('GL-NT-S')->assertSee('GL-NT-XL');

        $this->visit('/search?q=gloves')->assertSuccessful()->assertSee('Nitrile gloves')->assertDontSee('A4 copier paper');
    }

    public function test_sorting_orders_the_list_by_price(): void
    {
        $prices = function (string $query): array {
            $html = $this->visit('/shop'.$query)->assertSuccessful()->getContent();
            // The first price in each row, in page order.
            preg_match_all('/data-label="Price">\s*<span class="font-extrabold">₹([\d,]+)/u', $html, $m);

            return array_map(fn ($p) => (int) str_replace(',', '', $p), $m[1]);
        };

        $products = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->get();
        $cheapest = $products->min('price');
        $dearest = $products->max('price');

        $asc = $prices('?sort=price-asc');
        $this->assertEquals($cheapest, $asc[0], 'Low to high starts with the cheapest product.');
        $desc = $prices('?sort=price-desc');
        $this->assertEquals($dearest, $desc[0], 'High to low starts with the dearest product.');
    }

    public function test_the_sort_menu_is_a_real_form_not_an_inline_script(): void
    {
        // An inline onchange="new URL(…)" resolves URL to document.URL (a string) and throws, so the
        // menu changed but the page never reloaded. A GET form with a "sort" field cannot break that way.
        $html = $this->visit('/shop/cleaning')->assertSuccessful()->getContent();

        $this->assertMatchesRegularExpression('#<form method="get" action="[^"]*/shop/cleaning"#', $html);
        $this->assertStringContainsString('name="sort"', $html);
        $this->assertStringNotContainsString('onchange=', $html);
    }

    public function test_the_shop_search_narrows_the_list_within_the_category(): void
    {
        $this->visit('/shop?q=container')
            ->assertSuccessful()
            ->assertSee('name="q"', false)
            ->assertSee('orderSheet(', false)
            ->assertSee('PK-CT-750')
            ->assertSee('1 product')
            ->assertSee('4 sizes')
            ->assertDontSee('Floor cleaner concentrate');

        // A variant's own code finds its product.
        $this->visit('/shop?q=GL-NT-XL')->assertSuccessful()->assertSee('Nitrile gloves');

        // Searching inside a category stays inside it, and offers to widen the search.
        $this->visit('/shop/cleaning?q=container')
            ->assertSuccessful()
            ->assertSee('No products match')
            ->assertSee('Search all products');
    }

    public function test_the_menu_bar_is_the_same_on_every_page_and_does_not_repeat_categories(): void
    {
        // Up to the bar's own </nav>; the breadcrumb that follows names the category being browsed.
        $bar = fn (string $path) => (string) str($this->visit($path)->assertSuccessful()->getContent())->after('class="bk-catbar"')->before('</nav>');

        // Categories are listed on All products (and in the footer), not in the menu bar.
        foreach (['/', '/shop', '/shop/cleaning', '/track-order'] as $path) {
            $this->assertStringContainsString('All products', $bar($path), $path);
            $this->assertStringNotContainsString('Cleaning Chemicals', $bar($path), $path);
        }
        $this->visit('/')->assertSee('Cleaning Chemicals'); // still reachable from the footer
    }

    public function test_search_results_are_the_order_list_not_cards(): void
    {
        $this->visit('/search?q=round food container')
            ->assertSuccessful()
            ->assertSee('orderSheet(', false)
            ->assertDontSee('tradeCard(', false)
            ->assertSee('PK-CT-250')
            ->assertSee('PK-CT-1000');
    }

    public function test_collections_are_ready_made_order_lists_linked_from_the_menu(): void
    {
        // A tab beside All products, marked current on the collection pages.
        $tabs = fn (string $path) => (string) str($this->visit($path)->assertSuccessful()->getContent())->after('bk-catbar-main')->before('bk-catbar-side');
        $this->assertStringContainsString('>Collections</a>', $tabs('/'));
        $this->assertStringContainsString('class="is-on">Collections</a>', $tabs('/collections/takeaway-packing'));

        // A store with no collections has no tab leading to an empty page.
        Collection::withoutGlobalScopes()->where('client_id', $this->client->id)->update(['is_active' => false]);
        $this->assertStringNotContainsString('Collections', $tabs('/'));
        Collection::withoutGlobalScopes()->where('client_id', $this->client->id)->update(['is_active' => true]);

        $this->visit('/collections')
            ->assertSuccessful()
            ->assertSee('Restaurant kitchen essentials')
            ->assertSee('Takeaway &amp; delivery packing', false)
            ->assertSee('Office monthly restock');

        // A list a buyer can order in one go, with every size of a variant product as its own row.
        $this->visit('/collections/takeaway-packing')
            ->assertSuccessful()
            ->assertSee('orderSheet(', false)
            ->assertSee('Add all to order')
            ->assertSee('PK-CT-250')
            ->assertSee('PK-CT-1000')
            ->assertSee('PK-KB-M')
            ->assertDontSee('OF-A4-75');
    }

    public function test_a_sorted_list_is_not_split_into_repeating_category_headings(): void
    {
        $html = $this->visit('/shop?sort=price-asc')->assertSuccessful()->getContent();
        $this->assertStringNotContainsString('bk-group', $html, 'Sorted by price, categories interleave, so no heading rows.');

        $this->visit('/shop')->assertSee('bk-group', false);
    }

    public function test_the_product_page_lists_every_variant(): void
    {
        $this->visit('/product/'.$this->product('GL-NT')->slug)
            ->assertSuccessful()
            ->assertSee('GL-NT-S')
            ->assertSee('GL-NT-XL')
            ->assertSee('Out of stock');
    }

    public function test_a_signed_in_buyer_can_load_a_past_order_into_the_pad(): void
    {
        $customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha Rao', 'email' => 'asha@example.test', 'password' => 'secret-pass', 'is_active' => true]);
        $order = Order::create([
            'client_id' => $this->client->id, 'customer_id' => $customer->id,
            'number' => 'ME-9001', 'email' => $customer->email, 'status' => 'delivered',
            'shipping_name' => 'Asha Rao', 'shipping_line1' => '14 Nehru Road', 'shipping_city' => 'Pune',
            'shipping_state' => 'Maharashtra', 'shipping_postal_code' => '411001',
            'subtotal' => 1590, 'total' => 1590,
        ]);
        $order->items()->create(['product_id' => $this->product('GL-NT')->id, 'name' => 'Nitrile gloves', 'sku' => 'GL-NT', 'variant_sku' => 'GL-NT-M', 'price' => 420, 'qty' => 2, 'total' => 840]);
        $order->items()->create(['product_id' => $this->product('CL-FC-5L')->id, 'name' => 'Floor cleaner', 'sku' => 'CL-FC-5L', 'price' => 540, 'qty' => 1, 'total' => 540]);

        $this->actingAs($customer)->visit('/')
            ->assertSuccessful()
            ->assertSee('Order again')
            ->assertSee('ME-9001')
            ->assertSee('GL-NT-M, 2', false)
            ->assertSee('CL-FC-5L, 1', false);
    }

    public function test_an_order_placed_through_the_store_is_confirmed_with_its_codes(): void
    {
        $line = collect(OrderLines::for($this->product('PK-CT')))->firstWhere('sku', 'PK-CT-500');
        $this->order(['product_id' => $line['product_id'], 'options' => $line['options'], 'qty' => 4])->assertSuccessful();

        $placed = $this->withSession([Client::SESSION_KEY => 'trade'])->post('/checkout', [
            'email' => 'buyer@example.test', 'phone' => '9876543210',
            'shipping_name' => 'Hotel Saffron', 'shipping_line1' => '14 Nehru Road', 'shipping_city' => 'Pune',
            'shipping_state' => 'Maharashtra', 'shipping_postal_code' => '411001', 'shipping_country' => 'India',
            'shipping_method' => app(Cart::class)->shippingMethods()[0]['code'],
            'payment_method' => 'cod', 'notes' => 'PO-2291',
        ])->assertRedirect();

        $path = parse_url($placed->headers->get('Location'), PHP_URL_PATH);
        $this->visit($path)->assertSuccessful()->assertSee('Items ordered')->assertSee('PK-CT-500')->assertSee('4 × ₹265', false);
    }

    public function test_the_store_team_can_set_the_brand_colour_in_the_admin(): void
    {
        $admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Owner', 'email' => 'owner@trade.test', 'password' => 'secret-pass',
            'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        // What ApplyAdminScope does on a real panel request.
        app(StoreContext::class)->resolve($admin);

        Livewire::test(BulkOrderSettings::class)
            ->assertSuccessful()
            ->set('data.bulk_site.brand_color', '#7a1f5c')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('#7a1f5c', Setting::get('bulk_site.brand_color'));
    }

    public function test_the_sign_in_pages_use_the_store_layout_and_still_work(): void
    {
        foreach (['/login' => '/login', '/register' => '/register', '/forgot-password' => '/forgot-password'] as $path => $action) {
            $html = $this->visit($path)
                ->assertSuccessful()
                ->assertSee('bk-auth-panel', false)       // the store's own layout, not the shared one
                ->assertSee('Your trade account')
                ->assertDontSee('input-luxe', false)      // nothing from the shared design
                ->getContent();
            // Posts to the same route as the shared form, on this store's own address.
            $this->assertMatchesRegularExpression('#<form method="post" action="https?://[^"]+'.preg_quote($action, '#').'"#', $html);
        }

        // Registering and signing in go through the shared controller exactly as before.
        $this->withSession([Client::SESSION_KEY => 'trade'])->post('/register', [
            'name' => 'Hotel Saffron', 'email' => 'buyer@saffron.test',
            'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
        ])->assertRedirect(route('account.index'));
        $this->assertAuthenticated();

        auth()->logout();
        $this->withSession([Client::SESSION_KEY => 'trade'])->post('/login', ['email' => 'buyer@saffron.test', 'password' => 'secret-pass-123'])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticated();
    }

    public function test_both_sign_in_pages_have_a_picture(): void
    {
        // The store team's page (/client/login) takes the template's image unless the store sets its own.
        $team = Storefront::where('template', 'bulk')->firstOrFail()->templateObject()->teamLogin();
        $this->assertStringContainsString('images.unsplash.com', (string) $team['image']);

        // The customers' pages show the store's sign-in image behind the brand panel.
        $default = app(TemplateManager::class)->get('bulk')->defaults()['site']['login_image'];
        $this->visit('/login')->assertSee("--bk-auth-img: url('".e($default)."')", false);

        // A store's own upload replaces it.
        Setting::set('bulk_site', ['login_image' => 'https://cdn.example.test/our-warehouse.jpg'] + (Setting::get('bulk_site') ?? []));
        $this->visit('/register')->assertSee("--bk-auth-img: url('https://cdn.example.test/our-warehouse.jpg')", false);
        Setting::set('bulk_site', ['login_image' => "javascript:alert(1)')"] + Setting::get('bulk_site'));
        // Treated as a file name, with its quote and brackets encoded, so it cannot close url('…').
        $this->visit('/login')->assertDontSee("alert(1)')", false)->assertSee('alert%281%29%27', false);
    }

    public function test_no_page_falls_back_to_the_fashion_design(): void
    {
        // Views the store lacks are borrowed from the base (Fashion) folder, so render every page
        // a buyer can reach and check none of them is the Fashion layout.
        $customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha Rao', 'email' => 'asha@example.test', 'password' => 'secret-pass', 'is_active' => true]);
        $order = Order::create([
            'client_id' => $this->client->id, 'customer_id' => $customer->id, 'number' => 'ME-9002', 'email' => $customer->email,
            'status' => 'shipped', 'status_history' => [['status' => 'confirmed', 'at' => now()->subDay()->toIso8601String()], ['status' => 'shipped', 'at' => now()->toIso8601String()]],
            'shipping_name' => 'Asha Rao', 'shipping_line1' => '14 Nehru Road', 'shipping_city' => 'Pune', 'shipping_state' => 'Maharashtra',
            'shipping_postal_code' => '411001', 'subtotal' => 840, 'total' => 840, 'payment_method' => 'cod', 'payment_status' => 'cod',
        ]);
        $order->items()->create(['product_id' => $this->product('GL-NT')->id, 'name' => 'Nitrile gloves', 'sku' => 'GL-NT', 'variant_sku' => 'GL-NT-M', 'price' => 420, 'qty' => 2, 'total' => 840]);
        $collection = Collection::create(['client_id' => $this->client->id, 'template' => 'bulk', 'name' => 'Kitchen kit', 'slug' => 'kitchen-kit', 'is_active' => true]);
        $collection->products()->attach($this->product('CL-FC-5L')->id);
        Post::create(['client_id' => $this->client->id, 'template' => 'bulk', 'title' => 'Storing chemicals', 'slug' => 'storing-chemicals', 'body' => '<p>Keep them cool.</p>', 'published_at' => now()->subDay()]);
        foreach (['legal' => 'privacy', 'default' => 'delivery'] as $layout => $slug) {
            Page::create(['client_id' => $this->client->id, 'storefront_template' => 'bulk', 'title' => ucfirst($slug), 'slug' => $slug, 'template' => $layout, 'body' => '<h3>Section</h3><p>Text.</p>', 'is_active' => true]);
        }

        $pages = [
            '/track-order', '/track-order?number=ME-9002&email=asha@example.test', '/collections', '/collections/kitchen-kit',
            '/journal', '/journal/storing-chemicals', '/privacy', '/delivery',
        ];
        $signedIn = ['/account', '/account/orders', '/account/orders/ME-9002', '/account/addresses', '/account/profile', '/account/wishlist', '/order/ME-9002/confirmation'];

        foreach ($pages as $path) {
            $this->assertStoreDesign($this->visit($path), $path);
        }
        $this->actingAs($customer);
        foreach ($signedIn as $path) {
            $this->assertStoreDesign($this->visit($path), $path);
        }

        // The account has no wishlist link: this store has no wishlist.
        $this->visit('/account')->assertSee('Order again')->assertDontSee('Wishlist');
        $this->visit('/account/orders/ME-9002')->assertSee('GL-NT-M')->assertSee('Dispatched');
    }

    private function assertStoreDesign($response, string $path): void
    {
        $response->assertSuccessful();
        $html = $response->getContent();
        $this->assertStringContainsString('bk-wrap', $html, "{$path} should use this store's layout.");
        foreach (['container-luxe', 'input-luxe', 'prose-luxe', 'display-md', 'btn btn-primary'] as $fashion) {
            $this->assertStringNotContainsString($fashion, $html, "{$path} uses the Fashion design ({$fashion}).");
        }
    }

    public function test_phone_numbers_are_ten_digits(): void
    {
        $line = collect(OrderLines::for($this->product('PK-CT')))->firstWhere('sku', 'PK-CT-500');
        $this->order(['product_id' => $line['product_id'], 'options' => $line['options'], 'qty' => 1])->assertSuccessful();

        // Every phone field in the store is the checked one.
        foreach (['/checkout', '/register', '/contact'] as $path) {
            $this->visit($path)->assertSuccessful()
                ->assertSee('phoneField(', false)
                ->assertSee('name="phone" type="tel" inputmode="numeric"', false);
        }

        // The server takes the usual ways of writing a 10-digit number and stores just the digits…
        foreach (['9876543210', '98765 43210', '+91 98765 43210', '+91-98765-43210', '098765 43210', '919876543210'] as $ok) {
            $this->assertSame('9876543210', StorefrontRequest::normalisePhone($ok), $ok);
        }

        // …and refuses anything that is not 10 digits, with a message that says so.
        foreach (['12345', '98765432101', '98785456456444564654564', '98765abc43', '+1 415 555 0100'] as $bad) {
            $this->withSession([Client::SESSION_KEY => 'trade'])->from('/checkout')->post('/checkout', [
                'email' => 'buyer@example.test', 'phone' => $bad,
            ])->assertSessionHasErrors(['phone' => 'Please enter a 10-digit phone number, e.g. 9876543210.']);
        }
    }

    public function test_every_required_checkout_field_is_checked_in_the_browser(): void
    {
        $line = collect(OrderLines::for($this->product('PK-CT')))->firstWhere('sku', 'PK-CT-500');
        $this->order(['product_id' => $line['product_id'], 'options' => $line['options'], 'qty' => 1])->assertSuccessful();

        // Not just the phone: a missing email (or address…) shows its message too, before sending.
        $page = $this->visit('/checkout')->assertSuccessful();
        foreach (['email', 'full name', 'address', 'city', 'state', 'postal code', 'country'] as $attribute) {
            $page->assertSee('checkField(', false)->assertSee("'".$attribute."'", false);
        }
        foreach (['email', 'shipping_name', 'shipping_line1', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country'] as $name) {
            $page->assertSee('name="'.$name.'"', false);
        }

        // A server message still shows under its field.
        $this->withSession([Client::SESSION_KEY => 'trade'])->from('/checkout')->post('/checkout', ['phone' => '9876543210'])
            ->assertSessionHasErrors(['email' => 'Please enter your email address.']);
    }

    public function test_the_cart_checkout_and_contact_pages_render(): void
    {
        $line = collect(OrderLines::for($this->product('PK-CT')))->firstWhere('sku', 'PK-CT-500');
        $this->order(['product_id' => $line['product_id'], 'options' => $line['options'], 'qty' => 2])->assertSuccessful();

        $this->visit('/cart')->assertSuccessful()->assertSee('Your order');
        $this->visit('/checkout')->assertSuccessful()->assertSee('Place order');
        $this->visit('/contact')->assertSuccessful()->assertSee(route('contact.store'), false);
    }
}
