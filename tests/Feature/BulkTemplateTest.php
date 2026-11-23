<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Pages\BulkOrderSettings;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Http\Requests\Storefront\StorefrontRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Storefront;
use App\Services\Cart;
use App\Templates\Bulk\OrderLines;
use App\Templates\TemplateManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    public function test_every_policy_page_the_store_links_to_opens(): void
    {
        // Linked from the footer menu (privacy, terms, refund-policy) and the cookie banner.
        foreach (['/privacy', '/terms', '/refund-policy', '/cookies', '/contact'] as $path) {
            $this->visit($path)->assertOk();
        }

        $this->visit('/cookies')->assertSee('Your cookie choices')->assertSee('$store.cookies.save', false);
    }

    public function test_the_footer_shop_column_is_an_editable_menu(): void
    {
        $this->assertArrayHasKey('bulk_footer_shop', $this->client->templateObject()->locationLabels());

        $html = $this->visit('/')->assertOk()->getContent();
        $shop = Str::between($html, '<h3>Shop</h3>', '<h3>Help</h3>');
        foreach (['Bulk order', 'All products', 'Brands', 'Cleaning Chemicals', 'Office Supplies'] as $label) {
            $this->assertStringContainsString(e($label), $shop);
        }

        // Edited in Content → Navigation: the footer follows the menu, not the category list.
        $menu = Menu::where('location', 'bulk_footer_shop')->firstOrFail();
        $menu->allItems()->where('label', 'Office Supplies')->delete();
        $menu->allItems()->create(['label' => 'Trade price list', 'url' => '/price-list', 'sort_order' => 99]);
        Menu::flush();

        $shop = Str::between($this->visit('/')->getContent(), '<h3>Shop</h3>', '<h3>Help</h3>');
        $this->assertStringNotContainsString('Office Supplies', $shop);
        $this->assertStringContainsString('Trade price list', $shop);
        // No links (or no menu): no Shop column at all.
        $menu->allItems()->delete();
        Menu::flush();
        $this->visit('/')->assertOk()->assertDontSee('<h3>Shop</h3>', false)->assertSee('<h3>Help</h3>', false);

        $menu->forceDelete();
        Menu::flush();
        $this->visit('/')->assertOk()->assertDontSee('<h3>Shop</h3>', false);
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

    public function test_a_line_is_capped_by_its_stock_only(): void
    {
        $gloves = collect(OrderLines::for($this->product('GL-NT')))->keyBy('sku');

        // No fixed limit per line: as many as are in stock.
        $this->assertSame($gloves['GL-NT-M']['stock'], $gloves['GL-NT-M']['max']);
        $this->assertGreaterThan(20, $gloves['GL-NT-M']['max']);
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

    public function test_the_shared_cart_takes_more_than_twenty_of_a_line(): void
    {
        // The old fixed limit of 20 per line is gone, in every store.
        $line = collect(OrderLines::for($this->product('GL-NT')))->firstWhere('sku', 'GL-NT-M');
        $payload = ['product_id' => $line['product_id'], 'options' => $line['options']];

        $cart = $this->order($payload + ['qty' => 50])->assertSuccessful();
        $this->assertSame(50, $cart->json('items.0.qty'));

        $key = $cart->json('items.0.key');
        $this->withSession([Client::SESSION_KEY => 'trade'])->patchJson('/cart/items/'.$key, ['qty' => 120])->assertSuccessful();
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

    public function test_the_order_form_category_filter_lists_the_tree_with_each_parent_covering_its_children(): void
    {
        $sanitiser = $this->product('CL-HS-5L');
        $parent = $sanitiser->category;
        $child = Category::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'parent_id' => $parent->id, 'name' => 'Hospital Grade', 'slug' => 'hospital-grade', 'is_active' => true,
        ]);
        $sanitiser->update(['category_id' => $child->id]);

        $tree = null;
        $this->visit('/')->assertSuccessful()->assertViewHas('categoryTree', function ($rows) use (&$tree) {
            $tree = collect($rows)->keyBy('id');

            return true;
        });

        $this->assertSame(0, $tree[$parent->id]['depth']);
        $this->assertSame(1, $tree[$child->id]['depth']);
        $this->assertContains($sanitiser->id, $tree[$child->id]['products']);
        $this->assertContains($sanitiser->id, $tree[$parent->id]['products']); // choosing the parent shows its children's items
        $ids = $tree->keys()->all();
        $this->assertLessThan(array_search($child->id, $ids), array_search($parent->id, $ids)); // parent listed first
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

        // A category is a list to order down: a row per size, a quantity box each that goes straight into the cart.
        $this->visit('/shop/cleaning')
            ->assertSuccessful()
            ->assertSee('orderSheet(', false)
            ->assertDontSee('tradeCard(', false)
            ->assertSee('Floor cleaner concentrate')
            ->assertSee('CL-FC-5L')
            ->assertSee('View cart')
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
            ->assertSee('View cart')
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
        ])->assertRedirect(route('otp.verify'));

        // The code page is in the store's own layout; entering the code places the order.
        $this->visit('/verify')->assertSuccessful()->assertSee('bk-auth-panel', false)->assertSee('Confirm your order');
        $placed = $this->enterOtp(session: [Client::SESSION_KEY => 'trade'])->assertRedirect();

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

    public function test_the_all_products_sidebar_lists_every_visible_category_whatever_its_menu_switch(): void
    {
        $cleaning = Category::withoutGlobalScopes()->where('client_id', $this->client->id)->where('slug', 'cleaning')->firstOrFail();
        $cleaning->update(['show_in_menu' => false]);
        $sidebar = fn () => (string) str($this->visit('/shop')->assertSuccessful()->getContent())->after('aria-label="Categories"')->before('</aside>');

        $this->assertStringContainsString('/shop/cleaning', $sidebar());

        $cleaning->update(['is_active' => false]);
        $this->assertStringNotContainsString('/shop/cleaning', $sidebar());
    }

    public function test_the_category_admin_has_no_menu_and_homepage_switch_in_a_bulk_store(): void
    {
        $this->assertFalse(Category::featurableIn('bulk'));
        $this->assertTrue(Category::featurableIn('fashion'));
        $this->assertTrue(Category::featurableIn(null));

        $admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Owner', 'email' => 'owner@trade.test', 'password' => 'secret-pass',
            'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(StoreContext::class)->resolve($admin);

        Livewire::test(CreateCategory::class)->assertFormFieldHidden('show_in_menu')->assertFormFieldVisible('is_active');
        Livewire::test(ListCategories::class)->assertTableColumnHidden('show_in_menu');
    }

    public function test_the_sign_in_pages_use_the_store_layout_and_still_work(): void
    {
        foreach (['/login' => '/login', '/login/otp' => '/login/otp', '/register' => '/register', '/forgot-password' => '/forgot-password'] as $path => $action) {
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
            'name' => 'Hotel Saffron', 'email' => 'buyer@saffron.test', 'phone' => '9876543210',
            'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
        ])->assertRedirect(route('otp.verify'));
        $this->enterOtp(session: [Client::SESSION_KEY => 'trade'])->assertRedirect(route('account.index'));
        $this->assertAuthenticated();

        auth()->logout();
        $this->withSession([Client::SESSION_KEY => 'trade'])->post('/login', ['phone' => '+91 98765 43210', 'password' => 'secret-pass-123'])
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
            Page::updateOrCreate(['client_id' => $this->client->id, 'storefront_template' => 'bulk', 'slug' => $slug], ['title' => ucfirst($slug), 'template' => $layout, 'body' => '<h3>Section</h3><p>Text.</p>', 'is_active' => true]);
        }

        $pages = [
            '/track-order', '/track-order?number=ME-9002&email=asha@example.test', '/collections', '/collections/kitchen-kit',
            '/journal', '/journal/storing-chemicals', '/privacy', '/delivery', '/cookies',
        ];
        $signedIn = ['/account', '/account/orders', '/account/orders/ME-9002', '/account/addresses', '/account/profile', '/account/wishlist', '/order/ME-9002/confirmation'];

        foreach ($pages as $path) {
            $this->assertStoreDesign($this->visit($path), $path);
        }
        $this->actingAs($customer);
        foreach ($signedIn as $path) {
            $this->assertStoreDesign($this->visit($path), $path);
        }

        // The account links to Saved items (the wishlist) and counts them.
        $this->visit('/account')->assertSee('Order again')->assertSee('Saved items')->assertSee('$store.wishlist.count', false);
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

    public function test_products_can_be_saved_for_later_from_the_card_list_product_page_and_header(): void
    {
        $product = $this->product('GL-NT');

        // A heart on every product row (once per product, not per size) and in the header. The
        // heart passes the product's name too, for the "saved" message.
        $heart = '$store.wishlist.toggle('.$product->id.', ';
        $shop = $this->visit('/shop')->assertSuccessful()
            ->assertSee($heart, false)
            ->assertSee(route('account.wishlist'), false)
            ->assertSee('Saved items', false);
        $this->assertSame(1, substr_count($shop->getContent(), $heart));

        $this->visit('/product/'.$product->slug)->assertSee($heart, false);
    }

    public function test_the_saved_items_page_lists_saved_products_as_the_order_list(): void
    {
        $gloves = $this->product('GL-NT');
        $cleaner = $this->product('CL-FC-5L');

        // The page itself: works for guests, uses the store design and loads the list from the device.
        $page = $this->visit('/account/wishlist')->assertSuccessful()->assertSee('savedList(', false)->assertSee('Nothing saved yet');
        $this->assertStoreDesign($page, '/account/wishlist');
        $page->assertDontSee('Sign out');

        // The list it asks for: the saved products in the order saved, every size a row with a quantity box.
        $list = $this->visit('/account/wishlist?ids='.$cleaner->id.','.$gloves->id.',999999')->assertSuccessful()
            ->assertSee('orderSheet(', false)->assertSee('GL-NT-M')->assertSee('CL-FC-5L')->assertSee('View cart')
            ->assertDontSee('<html', false);
        $html = $list->getContent();
        $this->assertLessThan(strpos($html, 'GL-NT-M'), strpos($html, 'CL-FC-5L'), 'Saved items keep the order they were saved in.');

        // A product of another client is never shown, nor an inactive one.
        $cleaner->update(['is_active' => false]);
        $this->visit('/account/wishlist?ids='.$cleaner->id)->assertSuccessful()->assertDontSee('CL-FC-5L');
        $this->assertSame('', trim($this->visit('/account/wishlist?ids=')->getContent()));
    }

    public function test_a_signed_in_buyers_saved_items_are_kept_on_the_account(): void
    {
        $gloves = $this->product('GL-NT');
        $cleaner = $this->product('CL-FC-5L');
        $url = $this->client->publicUrl().'/account/wishlist';

        // A guest's list stays on the device: nothing to save to, and no account list on the page.
        $this->putJson($url, ['ids' => [$gloves->id]])->assertUnauthorized();
        $this->visit('/shop')->assertDontSee('account-wishlist', false);

        $customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha Rao', 'email' => 'asha@example.test', 'password' => 'secret-pass', 'is_active' => true]);
        $this->actingAs($customer);

        // Saved in the order sent; repeats and ids that are not this store's products are dropped.
        $this->putJson($url, ['ids' => [$cleaner->id, $gloves->id, $cleaner->id, 999999]])
            ->assertOk()->assertExactJson(['ids' => [$cleaner->id, $gloves->id]]);
        $this->assertSame([$cleaner->id, $gloves->id], $customer->fresh()->wishlist);

        // Every page hands the account's list to the wishlist store, to merge with the device's.
        $this->visit('/shop')->assertSee('<meta name="account-wishlist" content="['.$cleaner->id.','.$gloves->id.']" data-account="'.$customer->id.'">', false);

        // Emptied too.
        $this->putJson($url, ['ids' => []])->assertOk()->assertExactJson(['ids' => []]);
        $this->assertSame([], $customer->fresh()->wishlist);
        $this->putJson($url, ['ids' => 'nope'])->assertUnprocessable();
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

        // Not just the phone: a missing name (or address…) shows its message too, before sending. Email is optional.
        $page = $this->visit('/checkout')->assertSuccessful();
        foreach (['email', 'full name', 'address', 'city', 'state', 'postal code', 'country'] as $attribute) {
            $page->assertSee('checkField(', false)->assertSee("'".$attribute."'", false);
        }
        foreach (['email', 'shipping_name', 'shipping_line1', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country'] as $name) {
            $page->assertSee('name="'.$name.'"', false);
        }

        // A server message still shows under its field; a blank email is not one.
        $this->withSession([Client::SESSION_KEY => 'trade'])->from('/checkout')->post('/checkout', ['phone' => '9876543210'])
            ->assertSessionHasErrors(['shipping_name' => 'Please enter your full name.'])->assertSessionDoesntHaveErrors('email');
    }

    public function test_the_cart_checkout_and_contact_pages_render(): void
    {
        $line = collect(OrderLines::for($this->product('PK-CT')))->firstWhere('sku', 'PK-CT-500');
        $this->order(['product_id' => $line['product_id'], 'options' => $line['options'], 'qty' => 2])->assertSuccessful();

        $this->visit('/cart')->assertSuccessful()->assertSee('Cart')->assertDontSee('Your order');
        $this->visit('/checkout')->assertSuccessful()->assertSee('Place order');
        $this->visit('/contact')->assertSuccessful()->assertSee(route('contact.store'), false);
    }

    public function test_every_colour_can_be_chosen_on_the_product_page_by_its_name(): void
    {
        // Black and Red share the container photo, Blue has its own, White has none.
        $product = $this->product('PK-CT');
        $attributes = array_values(array_filter($product->variant_attributes, fn ($a) => ! preg_match('/^colou?rs?$/i', $a['name'])));
        $attributes[] = ['name' => 'Colour', 'values' => ['Black', 'White', 'Red', 'Blue'], 'images' => [
            'Black' => ['products/container.jpg'], 'Red' => ['products/container.jpg'], 'Blue' => ['products/dots.jpg'],
        ]];
        $product->update(['images' => ['products/container.jpg', 'products/dots.jpg'], 'variant_attributes' => $attributes]);

        $html = $this->visit(parse_url($product->fresh()->url, PHP_URL_PATH))->assertSuccessful()->getContent();
        $swatches = substr($html, strpos($html, 'aria-label="Available colours"'));
        $swatches = substr($swatches, 0, strpos($swatches, '</div>'));

        // Chosen by name, so picking Black does not also mark Red (same photo) as chosen.
        $this->assertStringContainsString("chosen === 'Black'", $swatches);
        $this->assertStringContainsString("chosen === 'Red'", $swatches);
        // White can be chosen too, and shows the product's own picture (no photo of its own).
        $this->assertStringContainsString("chosen === 'White'", $swatches);
        $this->assertStringNotContainsString('disabled', $swatches);
    }

    public function test_a_line_says_why_its_quantity_is_held_back(): void
    {
        $gloves = collect(OrderLines::for($this->product('GL-NT')))->keyBy('sku');
        $this->assertSame('Only '.$gloves['GL-NT-M']['max'].' left in stock.', $gloves['GL-NT-M']['limit_message']);
        $this->assertSame('This item is out of stock.', $gloves['GL-NT-XL']['limit_message']);

        $product = $this->product('PK-CT');
        $product->update(['max_qty' => 5]);
        $line = OrderLines::for($product->fresh())[0];
        $this->assertSame(5, $line['max']);
        $this->assertSame('You can order at most 5 of this item.', $line['limit_message']);

        // The + button stays clickable at the limit, so a click can say so.
        $html = $this->visit(parse_url($product->fresh()->url, PHP_URL_PATH))->assertSuccessful()->getContent();
        $this->assertStringContainsString('You can order at most 5 of this item.', $html);
        $this->assertStringNotContainsString(">= {$line['max']}\"", $html);
    }

    public function test_a_buyer_can_review_a_product_from_the_storefront(): void
    {
        $product = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->where('is_active', true)->firstOrFail();

        $this->visit('/product/'.$product->slug)->assertOk()
            ->assertSee('Ratings &amp; reviews', false)
            ->assertSee(route('products.reviews.store', $product->slug), false);

        $this->withSession([Client::SESSION_KEY => 'trade'])->post('/product/'.$product->slug.'/reviews', [
            'name' => 'Ravi Traders', 'rating' => 4, 'title' => 'Good stock', 'body' => 'Arrived on time and well packed for the shop.',
        ])->assertRedirect();

        $review = Review::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame($product->id, $review->product_id);
        $this->assertSame($this->client->id, $review->client_id);

        $review->update(['is_approved' => true]);
        $this->visit('/product/'.$product->slug)->assertSee('Arrived on time and well packed for the shop.');
    }

    public function test_the_review_section_goes_when_reviews_are_switched_off(): void
    {
        $product = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->where('is_active', true)->firstOrFail();
        $this->client->update(['modules' => ['orders']]);

        $this->visit('/product/'.$product->slug)->assertOk()
            ->assertDontSee('Ratings &amp; reviews', false)
            ->assertDontSee(route('products.reviews.store', $product->slug), false);
    }
}
