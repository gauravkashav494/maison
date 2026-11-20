<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Collection;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Departments (top-level categories: Men, Women) hold sub-categories (Shirts, Suits). A
 * department's page lists products at every depth below it and offers, as shortcuts, the
 * collections that hold products on that page. Collections are not tied to categories.
 */
class DepartmentCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private string $template = 'bulk';

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Bulk', 'slug' => 'bulk', 'template' => $this->template, 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function category(string $name, ?Category $parent = null): Category
    {
        $slug = str(($parent ? $parent->slug.' ' : '').$name)->slug()->value();

        return Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => $this->template, 'parent_id' => $parent?->id, 'name' => $name, 'slug' => $slug, 'is_active' => true, 'show_in_menu' => true]);
    }

    private function collection(string $name): Collection
    {
        return Collection::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => $this->template, 'name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true]);
    }

    private function product(string $name, Category $category, ?Collection $collection = null): Product
    {
        $product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => $this->template, 'category_id' => $category->id,
            'name' => $name, 'slug' => str($name)->slug()->value(), 'sku' => strtoupper(str($name)->slug()->value()), 'price' => 1500, 'stock' => 5, 'is_active' => true,
        ]);
        $collection?->products()->attach($product->id);

        return $product;
    }

    public function test_a_category_knows_its_department(): void
    {
        $men = $this->category('Men');

        $this->assertTrue($this->category('Formal', $this->category('Shirts', $men))->root()->is($men));
        $this->assertTrue($men->root()->is($men));
    }

    public function test_a_department_page_lists_products_at_every_depth_and_the_collections_they_are_in(): void
    {
        $men = $this->category('Men');
        $women = $this->category('Women');
        $shirts = $this->category('Shirts', $men);
        $formal = $this->category('Formal Shirts', $shirts);
        $summer = $this->collection('Summer');
        $festive = $this->collection('Festive');
        $this->product('Oxford Shirt', $formal, $summer);
        $this->product('Silk Kurta', $this->category('Kurtas', $women), $festive);
        $url = $this->client->publicUrl();

        $this->get($url.'/shop/men')->assertSuccessful()
            ->assertSee('Oxford Shirt')          // two levels down
            ->assertDontSee('Silk Kurta')
            ->assertDontSee('All Men')           // no sub-category chips: the side list has them
            ->assertSee('?collection=summer', false)      // holds a product here
            ->assertDontSee('?collection=festive', false); // holds none

        $this->get($url.'/shop/men-shirts')->assertSuccessful()->assertSee('Oxford Shirt')->assertSee('?collection=summer', false);
        $this->get($url.'/shop/women')->assertSuccessful()->assertSee('?collection=festive', false)->assertDontSee('?collection=summer', false);
        $this->get($url.'/shop/women?collection=summer')->assertSuccessful()->assertDontSee('Silk Kurta');
        $this->get($url.'/shop/men?collection=summer')->assertSuccessful()->assertSee('Oxford Shirt');
    }
}
