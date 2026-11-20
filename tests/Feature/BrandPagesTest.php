<?php

namespace Tests\Feature;

use App\Clients\ClientModules;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Client;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Database\Seeders\BulkMenuSeeder;
use Database\Seeders\GroceryMenuSeeder;
use Database\Seeders\HeritageMenuSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PlumbingMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every product store has a page per brand, /brands/{slug}: the shop listing of that brand's
 * products, headed by its description, with its own search title. Product pages and homepage
 * brand sections link to it, the sitemap lists it, and switching the Brands module off closes it.
 */
class BrandPagesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Brand $brand;

    private Product $branded;

    private Product $other;

    private function makeStore(string $template): void
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => ucfirst($template).' Store', 'slug' => $template, 'template' => $template, 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $own = ['client_id' => $this->client->id, 'template' => $template];
        $category = Category::withoutGlobalScopes()->create($own + ['name' => 'Taps', 'slug' => 'taps', 'is_active' => true]);
        $this->brand = Brand::withoutGlobalScopes()->create($own + [
            'name' => 'Jaquar', 'slug' => 'jaquar', 'description' => 'Bathroom fittings made in India since 1960.',
            'meta_title' => 'Jaquar taps and showers',
        ]);
        $this->branded = Product::withoutGlobalScopes()->create($own + [
            'name' => 'Jaquar Pillar Tap', 'slug' => 'jaquar-pillar-tap', 'category_id' => $category->id, 'price' => 2400, 'stock' => 10,
            'is_active' => true, 'images' => [], 'brand_id' => $this->brand->id, 'brand' => 'Jaquar',
        ]);
        $this->other = Product::withoutGlobalScopes()->create($own + [
            'name' => 'Cera Basin Mixer', 'slug' => 'cera-basin-mixer', 'category_id' => $category->id, 'price' => 3100, 'stock' => 10,
            'is_active' => true, 'images' => [],
        ]);
    }

    private function store()
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha']);
    }

    /** @return array<string, array{string}> */
    public static function productStores(): array
    {
        return ['fashion' => ['fashion'], 'grocery' => ['grocery'], 'heritage' => ['heritage'], 'plumbing' => ['plumbing'], 'bulk' => ['bulk']];
    }

    #[DataProvider('productStores')]
    public function test_every_product_store_has_a_page_per_brand(string $template): void
    {
        $this->makeStore($template);

        $this->store()->get('/brands/jaquar')
            ->assertOk()
            ->assertSee('Jaquar Pillar Tap')
            ->assertDontSee('Cera Basin Mixer')
            ->assertSee('Bathroom fittings made in India since 1960.')
            ->assertSee('<title>Jaquar taps and showers', false)
            ->assertSee('"@type":"Brand"', false);

        if ($template !== 'bulk') { // the bulk shop has no breadcrumbs
            $this->store()->get('/brands/jaquar')->assertSee('href="'.route('brands.index').'"', false);
        }
    }

    #[DataProvider('productStores')]
    public function test_every_product_store_lists_its_brands(string $template): void
    {
        $this->makeStore($template);
        Brand::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => $template, 'name' => 'Empty Co', 'slug' => 'empty-co']);

        $this->store()->get('/brands')
            ->assertOk()
            ->assertSee('Jaquar')
            ->assertSee(route('brands.show', 'jaquar'), false)
            ->assertSee('Bathroom fittings made in India since 1960.')
            ->assertDontSee('Empty Co'); // nothing on sale
    }

    #[DataProvider('productStores')]
    public function test_the_product_page_links_to_its_brand_page(string $template): void
    {
        $this->makeStore($template);

        $this->store()->get('/product/jaquar-pillar-tap')
            ->assertOk()
            ->assertSee(route('brands.show', 'jaquar'), false);
    }

    /** @return array<string, array{class-string, string}> */
    public static function headerMenus(): array
    {
        return [
            'fashion' => [MenuSeeder::class, 'header'],
            'grocery' => [GroceryMenuSeeder::class, 'grocery_header'],
            'heritage' => [HeritageMenuSeeder::class, 'heritage_header'],
            'plumbing' => [PlumbingMenuSeeder::class, 'plumbing_header'],
            'bulk' => [BulkMenuSeeder::class, 'bulk_header'],
        ];
    }

    #[DataProvider('headerMenus')]
    public function test_every_store_header_menu_starts_with_a_brands_link(string $seeder, string $location): void
    {
        $this->seed($seeder);

        $menu = Menu::withoutGlobalScopes()->where('location', $location)->firstOrFail();
        $this->assertSame(['Brands'], $menu->allItems()->where('url', '/brands')->pluck('label')->all());
    }

    public function test_existing_store_menus_are_given_the_brands_link_once(): void
    {
        // Menus as they were before: Grocery without the link, Plumbing with the old shop-filter link.
        $this->seed(GroceryMenuSeeder::class);
        $this->seed(PlumbingMenuSeeder::class);
        MenuItem::query()->where('url', '/brands')->whereHas('menu', fn ($q) => $q->withoutGlobalScopes()->where('location', 'grocery_header'))->delete();
        MenuItem::query()->where('url', '/brands')->update(['url' => '/shop?view=brands']);

        $migration = require database_path('migrations/2026_11_20_130000_add_brands_to_header_menus.php');
        $migration->up();
        $migration->up(); // running again adds nothing

        $labels = fn (string $location) => Menu::withoutGlobalScopes()->where('location', $location)->firstOrFail()
            ->items()->pluck('label')->all();
        $this->assertSame(['Offers', 'Bestsellers', 'New in store', 'Combos', 'Brands', 'Recipes & tips'], $labels('grocery_header'));
        $this->assertSame(['Shop all', 'Brands', 'Offers', 'Project kits', 'Bulk quote'], $labels('plumbing_header'));
        $this->assertSame(0, MenuItem::query()->where('url', '/shop?view=brands')->count());
    }

    public function test_an_unknown_brand_goes_to_the_shop(): void
    {
        $this->makeStore('plumbing');

        $this->store()->get('/brands/nope')->assertRedirect(route('shop.index'));
    }

    public function test_the_sitemap_lists_brands_with_products_on_sale(): void
    {
        $this->makeStore('plumbing');
        Brand::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'plumbing', 'name' => 'Empty Co', 'slug' => 'empty-co']);

        $this->store()->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.route('brands.index').'</loc>', false)
            ->assertSee(route('brands.show', 'jaquar'), false)
            ->assertDontSee(route('brands.show', 'empty-co'), false);

        $this->brand->update(['noindex' => true]);
        $this->store()->get('/sitemap.xml')->assertDontSee(route('brands.show', 'jaquar'), false);
    }

    public function test_switching_brands_off_closes_the_pages_and_links_fall_back_to_the_filter(): void
    {
        $this->makeStore('plumbing');
        $keep = array_diff(array_keys(ClientModules::forClient($this->client)), ['brands']);
        $this->client->update(['modules' => array_values($keep)]);

        $this->store()->get('/brands/jaquar')->assertNotFound();
        $this->store()->get('/brands')->assertNotFound();
        $this->store()->get('/product/jaquar-pillar-tap')
            ->assertOk()
            ->assertDontSee(route('brands.show', 'jaquar'), false)
            ->assertSee(e(route('shop.index', ['brand' => 'Jaquar'])), false);
    }
}
