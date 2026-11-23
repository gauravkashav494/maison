<?php

namespace Tests\Feature;

use App\Clients\ClientModules;
use App\Models\Category;
use App\Models\Client;
use App\Models\Collection;
use App\Models\Coupon;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Product;
use App\Models\Storefront;
use App\Templates\TemplateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A module switched off on the client is off on its public store too: its pages and forms
 * answer 404, its records read as empty and menu links into it are left out. Core modules
 * and modules without a storefront side (Pages, Navigation) are untouched.
 */
class StoreModulesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $own = ['client_id' => $this->client->id, 'template' => 'fashion'];
        $category = Category::create($own + ['name' => 'Sarees', 'slug' => 'sarees', 'is_active' => true]);
        $product = Product::create($own + ['name' => 'Banarasi Saree', 'slug' => 'banarasi-saree', 'category_id' => $category->id, 'price' => 18900, 'stock' => 10, 'is_active' => true, 'images' => []]);
        $collection = Collection::create($own + ['name' => 'Festive Edit', 'slug' => 'festive-edit', 'is_active' => true]);
        $collection->products()->attach($product);
        Post::create($own + ['title' => 'Draping a saree', 'slug' => 'draping-a-saree', 'excerpt' => 'How to', 'body' => '<p>Body</p>', 'published_at' => now()->subDay()]);
        Page::create(['client_id' => $this->client->id, 'storefront_template' => 'fashion', 'title' => 'FAQ', 'slug' => 'faq', 'template' => 'faq', 'is_active' => true]);
        Page::create(['client_id' => $this->client->id, 'storefront_template' => 'fashion', 'title' => 'Our story', 'slug' => 'our-story', 'template' => 'default', 'is_active' => true]);
        Coupon::create(['client_id' => $this->client->id, 'code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'is_active' => true]);
    }

    private function switchOff(string ...$modules): void
    {
        $keep = array_diff(array_keys(ClientModules::forClient($this->client)), $modules);
        $this->client->update(['modules' => array_values($keep)]);
    }

    private function store()
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha']);
    }

    public function test_every_module_is_on_by_default(): void
    {
        $this->store()->get('/collections')->assertOk()->assertSee('Festive Edit');
        $this->store()->get('/collections/festive-edit')->assertOk();
        $this->store()->get('/journal')->assertOk()->assertSee('Draping a saree');
        $this->store()->get('/faq')->assertOk();
    }

    public function test_switched_off_pages_answer_404(): void
    {
        $this->switchOff('collections', 'posts', 'faqs');

        $this->store()->get('/collections')->assertNotFound();
        $this->store()->get('/collections/festive-edit')->assertNotFound();
        $this->store()->get('/journal')->assertNotFound();
        $this->store()->get('/journal/draping-a-saree')->assertNotFound();
        $this->store()->get('/faq')->assertNotFound();

        // Core modules and plain CMS pages are untouched.
        $this->store()->get('/product/banarasi-saree')->assertOk();
        $this->store()->get('/our-story')->assertOk();
    }

    public function test_switched_off_forms_refuse_submissions(): void
    {
        $this->switchOff('reviews', 'subscribers', 'messages', 'coupons');

        $this->store()->post('/product/banarasi-saree/reviews', ['name' => 'R', 'rating' => 5, 'title' => 'Nice', 'body' => 'A lovely saree, really.'])->assertNotFound();
        $this->store()->postJson('/newsletter', ['email' => 'a@b.test'])->assertNotFound();
        $this->store()->post('/contact', ['name' => 'A', 'email' => 'a@b.test', 'message' => 'Hello there'])->assertNotFound();
        $this->store()->postJson('/cart/coupon', ['code' => 'WELCOME10'])->assertNotFound();
    }

    public function test_a_coupon_applied_before_the_module_went_off_stops_counting(): void
    {
        $this->store()->postJson('/cart/items', ['product_id' => Product::withoutGlobalScopes()->value('id'), 'qty' => 1])->assertOk();
        $this->store()->postJson('/cart/coupon', ['code' => 'WELCOME10'])->assertOk();
        $this->assertNotNull($this->store()->getJson('/cart/items')->json('coupon'));

        $this->switchOff('coupons');

        $this->assertNull($this->store()->getJson('/cart/items')->json('coupon'));
    }

    public function test_switched_off_records_leave_the_shop_pages(): void
    {
        $this->store()->get('/sitemap.xml')->assertSee('festive-edit')->assertSee('draping-a-saree');
        $this->store()->get('/shop')->assertOk()->assertSee('Festive Edit');

        $this->switchOff('collections', 'posts');

        $this->store()->get('/sitemap.xml')->assertDontSee('festive-edit')->assertDontSee('draping-a-saree')
            ->assertDontSee('/collections<', false)->assertDontSee('/journal<', false)->assertSee('/shop<', false);
        $this->store()->get('/shop')->assertOk()->assertDontSee('Festive Edit');
    }

    public function test_menu_links_into_a_switched_off_module_are_left_out(): void
    {
        $location = array_values(app(TemplateManager::class)->get('fashion')->menuLocations())[0][0];
        $menu = Menu::create(['client_id' => $this->client->id, 'name' => 'Main', 'location' => $location]);
        foreach (['Collections' => '/collections', 'Journal' => '/journal', 'Our story' => '/our-story', 'Shop' => '/shop'] as $label => $url) {
            MenuItem::create(['client_id' => $this->client->id, 'menu_id' => $menu->id, 'label' => $label, 'url' => $url, 'is_active' => true]);
        }

        $this->switchOff('collections');

        $html = $this->store()->get('/our-story')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="/collections"', $html);
        $this->assertStringContainsString('href="/journal"', $html);
        $this->assertStringContainsString('href="/our-story"', $html);
    }
}
