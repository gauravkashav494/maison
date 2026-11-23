<?php

namespace Tests\Feature;

use App\Filament\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ViewCategory;
use App\Filament\Resources\Collections\Pages\ViewCollection;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\RelationManagers\ReviewsRelationManager;
use App\Models\Address;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Collection;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Review;
use App\Models\Storefront;
use App\Support\IndianStates;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Detail (view) pages for customers, products, categories and collections: each opens for the
 * client's team, shows the record's facts, and lists its related records below.
 */
class DetailPagesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private ClientUser $admin;

    private Category $category;

    private Category $child;

    private Product $product;

    private Product $other;

    private Collection $collection;

    private Customer $customer;

    private Order $order;

    private Order $cancelled;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        $own = ['client_id' => $this->client->id, 'template' => 'fashion'];
        $this->category = Category::create($own + ['name' => 'Sarees', 'slug' => 'sarees', 'tagline' => 'Handwoven silks', 'is_active' => true]);
        $this->child = Category::create($own + ['name' => 'Silk Sarees', 'slug' => 'silk-sarees', 'parent_id' => $this->category->id, 'is_active' => true]);
        $this->product = Product::create($own + [
            'name' => 'Kanjivaram Bridal Saree', 'slug' => 'kanjivaram-bridal-saree', 'sku' => 'EF-1001', 'category_id' => $this->category->id,
            'price' => 34500, 'compare_at_price' => 38900, 'stock' => 3, 'is_active' => true, 'brand' => 'Ethnic Fashion Store',
            'colors' => [['name' => 'Red', 'hex' => '#B0182A']], 'sizes' => ['Free size'], 'images' => [], 'rating' => 5, 'review_count' => 1,
        ]);
        $this->other = Product::create($own + ['name' => 'Chanderi Saree', 'slug' => 'chanderi-saree', 'category_id' => $this->category->id, 'price' => 8900, 'stock' => 20, 'is_active' => true, 'images' => []]);
        $this->collection = Collection::create($own + ['name' => 'The Wedding Edit', 'slug' => 'wedding-edit', 'season' => 'Wedding Season', 'is_active' => true]);
        $this->collection->products()->sync([$this->product->id => ['sort_order' => 0]]);

        Review::create($own + ['product_id' => $this->product->id, 'name' => 'Ananya', 'rating' => 5, 'title' => 'Wore it for my wedding', 'body' => 'Beautiful.', 'is_approved' => true]);

        $this->customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Ananya Iyer', 'email' => 'ananya@example.com', 'phone' => '+91 98123 40000', 'password' => 'password123', 'is_active' => true]);
        Address::create(['customer_id' => $this->customer->id, 'label' => 'Home', 'name' => 'Ananya Iyer', 'line1' => '14 Boat Club Road', 'city' => 'Chennai', 'state' => 'Tamil Nadu', 'postal_code' => '600028', 'country' => 'India', 'is_default' => true]);

        $this->order = $this->order('EF-0001', 'delivered', 34500);
        $this->cancelled = $this->order('EF-0002', 'cancelled', 8900);
        OrderItem::create(['order_id' => $this->order->id, 'product_id' => $this->product->id, 'name' => $this->product->name, 'price' => 34500, 'qty' => 1, 'total' => 34500]);
        OrderItem::create(['order_id' => $this->cancelled->id, 'product_id' => $this->product->id, 'name' => $this->product->name, 'price' => 34500, 'qty' => 2, 'total' => 69000]);
    }

    private function order(string $number, string $status, int $total): Order
    {
        return Order::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'customer_id' => $this->customer->id, 'number' => $number,
            'email' => $this->customer->email, 'status' => $status, 'payment_method' => 'upi',
            'shipping_name' => 'Ananya Iyer', 'shipping_line1' => '14 Boat Club Road', 'shipping_city' => 'Chennai',
            'shipping_state' => 'Tamil Nadu', 'shipping_postal_code' => '600028', 'shipping_country' => 'India',
            'subtotal' => $total, 'total' => $total,
        ]);
    }

    private function page(string $url)
    {
        return $this->actingAs($this->admin, 'client')->get($url)->assertSuccessful();
    }

    private function relation(string $manager, $owner, string $page)
    {
        $this->actingAs($this->admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));

        return Livewire::test($manager, ['ownerRecord' => $owner, 'pageClass' => $page]);
    }

    public function test_product_detail_shows_price_stock_variants_and_sales(): void
    {
        $this->page('/client/products/'.$this->product->id)
            ->assertSee('Kanjivaram Bridal Saree')
            ->assertSee('EF-1001')
            ->assertSee('Sarees')
            ->assertSee('3 in stock')
            ->assertSee('The Wedding Edit')
            ->assertSee('Free size')
            // Sales leave out the cancelled order: 1 unit, ₹34,500.
            ->assertSee(money(34500))
            ->assertDontSee(money(103500))
            ->assertSee('View on store');

        $this->relation(ReviewsRelationManager::class, $this->product, ViewProduct::class)
            ->assertSee('Wore it for my wedding');
    }

    public function test_category_detail_shows_its_sub_categories_and_products(): void
    {
        $this->page('/client/categories/'.$this->category->id)
            ->assertSee('Sarees')
            ->assertSee('Handwoven silks')
            ->assertSee('Silk Sarees')
            ->assertSee('/shop/');

        $this->relation(ProductsRelationManager::class, $this->category, ViewCategory::class)
            ->assertSee('Kanjivaram Bridal Saree')
            ->assertSee('Chanderi Saree')
            ->assertSee('/client/products/'.$this->product->id, false);
    }

    public function test_collection_detail_lists_only_its_own_products(): void
    {
        $this->page('/client/collections/'.$this->collection->id)
            ->assertSee('The Wedding Edit')
            ->assertSee('Wedding Season');

        $this->relation(ProductsRelationManager::class, $this->collection, ViewCollection::class)
            ->assertSee('Kanjivaram Bridal Saree')
            ->assertDontSee('Chanderi Saree');
    }

    public function test_customer_detail_shows_contact_addresses_spend_and_orders(): void
    {
        $this->page('/client/customers/'.$this->customer->id)
            ->assertSee('Ananya Iyer')
            ->assertSee('ananya@example.com')
            ->assertSee('14 Boat Club Road')
            ->assertSee('Default')
            // Total spent leaves out the cancelled order.
            ->assertSee(money(34500))
            ->assertDontSee(money(43400));

        $this->relation(OrdersRelationManager::class, $this->customer, ViewCustomer::class)
            ->assertSee('EF-0001')
            ->assertSee('EF-0002')
            ->assertSee('/client/orders/'.$this->order->id, false);
    }

    public function test_the_menu_in_each_list_links_to_the_detail_page(): void
    {
        foreach ([
            '/client/products' => '/client/products/'.$this->product->id.'"',
            '/client/categories' => '/client/categories/'.$this->category->id.'"',
            '/client/collections' => '/client/collections/'.$this->collection->id.'"',
            '/client/customers' => '/client/customers/'.$this->customer->id.'"',
        ] as $list => $detail) {
            $this->assertStringContainsString($detail, $this->page($list)->getContent(), "{$list} should link to the detail page from ⋮ → View");
        }
    }

    public function test_the_related_lists_do_not_show_on_the_edit_pages(): void
    {
        $this->assertFalse(ProductsRelationManager::canViewForRecord($this->category, EditCategory::class));
        $this->assertFalse(OrdersRelationManager::canViewForRecord($this->customer, EditCustomer::class));
    }

    public function test_the_products_list_filters_by_collection(): void
    {
        $url = fn (array $values) => '/client/products?'.http_build_query(['filters' => ['collections' => ['values' => $values]]]);

        // In the Wedding Edit: only the Kanjivaram saree.
        $this->page($url([$this->collection->id]))
            ->assertSee('Kanjivaram Bridal Saree')->assertDontSee('Chanderi Saree');

        // In no collection at all: only the Chanderi saree.
        $this->page($url(['none']))
            ->assertSee('Chanderi Saree')->assertDontSee('Kanjivaram Bridal Saree');

        // Either: both.
        $this->page($url([$this->collection->id, 'none']))
            ->assertSee('Chanderi Saree')->assertSee('Kanjivaram Bridal Saree');
    }

    /**
     * Every table's filters sit in a bar above the table that is always open (AppServiceProvider),
     * with the fields side by side; each filter applies as soon as it changes.
     */
    public function test_every_table_has_its_filters_open_above_it(): void
    {
        foreach (['/client/products', '/client/orders', '/client/categories'] as $url) {
            $html = $this->page($url)->getContent();
            $start = strpos($html, 'fi-ta-filters-above-content-ctn');
            $this->assertNotFalse($start, "{$url} has its filters above the table");
            $panel = substr($html, $start); // the bar and what follows it

            $this->assertStringNotContainsString('x-show="areFiltersOpen"', $panel, "{$url} bar is always open");
            $this->assertStringNotContainsString('fi-ta-filters-trigger-action-ctn', $panel, "{$url} has no Filters button");
            $this->assertStringNotContainsString('fi-ta-filters-dropdown', $html, "{$url} has no filters dropdown");
            // Filters apply as they change: no Apply button, Reset at the end of the fields.
            $this->assertStringNotContainsString('wire:click="applyTableFilters"', $panel, "{$url} has no Apply button");
            $this->assertStringContainsString("path: 'tableFilters.", $panel, "{$url} filters apply as they change");
            $this->assertStringNotContainsString("path: 'tableDeferredFilters.", $panel, "{$url} no filter waits for Apply");
            $actions = substr($panel, (int) strpos($panel, 'fi-ta-filters-actions-ctn'), 3000);
            $this->assertStringContainsString('wire:click="resetTableFiltersForm"', $actions, "{$url} Reset at the end of the fields");
            foreach (['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4] as $breakpoint => $count) {
                $this->assertMatchesRegularExpression("/--cols-{$breakpoint}:\\s*repeat\\({$count},/", $panel, "{$url} {$breakpoint} columns");
            }
        }

        // A date range is one picker with ready ranges (App\Filament\Support\DateRangeFilter),
        // not a From and an Until field.
        $html = $this->page('/client/orders')->getContent();
        $this->assertStringContainsString('Placed between', $html);
        $this->assertStringContainsString('dateRangeComponent', $html);
        $this->assertStringNotContainsString('Placed until', $html);
    }

    /**
     * Every select opens the panel's styled list; long lists and lists read from the database
     * can be searched, a short fixed list cannot (AppServiceProvider::registerSelects()).
     */
    public function test_selects_are_styled_and_long_lists_are_searchable(): void
    {
        $short = Select::make('status')->options(['a' => 'A', 'b' => 'B', 'c' => 'C']);
        $long = Select::make('state')->options(IndianStates::options());
        $related = Select::make('brand_id')->relationship('brandRecord', 'name');
        $own = Select::make('pick')->options(['a' => 'A'])->searchable()->native(true);

        $this->assertFalse($short->isNative());
        $this->assertFalse($short->isSearchable());
        $this->assertTrue($long->isSearchable());
        $this->assertTrue($related->isSearchable());
        $this->assertTrue($related->isPreloaded());
        $this->assertTrue($own->isSearchable(), 'a select that chose for itself keeps its choice');

        $filter = SelectFilter::make('payment_method')->options(['upi' => 'UPI', 'cod' => 'Cash on delivery']);
        $this->assertFalse($filter->isNative());
        $this->assertFalse((bool) $filter->getSearchable());
        $this->assertTrue((bool) SelectFilter::make('state')->options(IndianStates::options())->getSearchable());

        // An empty Yes / No filter reads "All", not Filament's "-"; Deleted items keeps its words.
        $this->assertSame('All', TernaryFilter::make('is_active')->getPlaceholder());
        $this->assertSame('Hide deleted', TrashedFilter::make()->getPlaceholder());
    }
}
