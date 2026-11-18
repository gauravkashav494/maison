<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Collection;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A product is listed under several categories (one of them its main category: card label and
 * breadcrumb) and sits in at most one collection.
 */
class ProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Category $shirts;

    private Category $linen;

    private Category $sale;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $category = fn (string $name) => Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true, 'show_in_menu' => true]);
        $this->shirts = $category('Shirts');
        $this->linen = $category('Linen');
        $this->sale = $category('Gifts');
    }

    private function signIn(): void
    {
        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    private function collection(string $name): Collection
    {
        return Collection::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $this->shirts->id,
            'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'price' => 2000, 'stock' => 5, 'is_active' => true,
        ]);
    }

    public function test_the_form_saves_several_categories_and_the_first_is_the_main_one(): void
    {
        $this->signIn();
        $summer = $this->collection('Summer');

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'categories' => [$this->linen->id, $this->sale->id], 'collection' => $summer->id, 'price_excl_tax' => 2000])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::withoutGlobalScopes()->where('slug', 'linen-shirt')->firstOrFail();
        $this->assertSame($this->linen->id, $product->category_id);
        $this->assertEqualsCanonicalizing([$this->linen->id, $this->sale->id], $product->categories()->pluck('categories.id')->all());
        $this->assertSame([$summer->id], $product->collections()->pluck('collections.id')->all());
    }

    public function test_at_least_one_category_is_required(): void
    {
        $this->signIn();

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'categories' => [], 'price_excl_tax' => 2000])
            ->call('create')
            ->assertHasFormErrors(['categories' => 'required']);
    }

    public function test_editing_keeps_the_main_category_until_it_is_removed(): void
    {
        $product = $this->product();
        $product->categories()->attach($this->sale->id);
        $this->signIn();

        // Adding a category leaves the main one alone, whatever order the picker holds them in.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSet('data.categories', fn ($ids) => collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all() === collect([$this->shirts->id, $this->sale->id])->sort()->values()->all())
            ->fillForm(['categories' => [$this->sale->id, $this->linen->id, $this->shirts->id]])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($this->shirts->id, $product->fresh()->category_id);
        $this->assertCount(3, $product->categories()->get());

        // Removing the main category makes the first remaining one main.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['categories' => [$this->linen->id, $this->sale->id]])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($this->linen->id, $product->fresh()->category_id);
        $this->assertEqualsCanonicalizing([$this->linen->id, $this->sale->id], $product->categories()->pluck('categories.id')->all());
    }

    public function test_the_collection_is_single_and_can_be_changed_or_cleared(): void
    {
        $summer = $this->collection('Summer');
        $winter = $this->collection('Winter');
        $product = $this->product();
        $product->collections()->attach($summer->id, ['sort_order' => 3]);
        $this->signIn();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSet('data.collection', $summer->id)
            ->fillForm(['collection' => $winter->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame([$winter->id], $product->collections()->pluck('collections.id')->all());

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['collection' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(0, $product->collections()->count());
    }

    public function test_adding_a_product_from_the_collection_form_moves_it_there(): void
    {
        $summer = $this->collection('Summer');
        $winter = $this->collection('Winter');
        $product = $this->product();
        $product->collections()->attach($summer->id);
        $this->signIn();

        Livewire::test(EditCollection::class, ['record' => $winter->getRouteKey()])
            ->fillForm(['products' => [$product->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$winter->id], $product->collections()->pluck('collections.id')->all());
    }

    public function test_keeping_the_same_collection_keeps_the_products_place_in_it(): void
    {
        $summer = $this->collection('Summer');
        $product = $this->product();
        $product->collections()->attach($summer->id, ['sort_order' => 7]);
        $this->signIn();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Linen Shirt II'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(7, (int) $product->collections()->first()->pivot->sort_order);
    }

    public function test_writing_only_the_main_category_also_lists_the_product_under_it(): void
    {
        $product = $this->product();

        $this->assertSame([$this->shirts->id], $product->categories()->pluck('categories.id')->all());

        $product->update(['category_id' => $this->linen->id]);
        $this->assertEqualsCanonicalizing([$this->shirts->id, $this->linen->id], $product->categories()->pluck('categories.id')->all());
    }

    public function test_the_storefront_lists_the_product_under_every_category(): void
    {
        $product = $this->product();
        $product->categories()->attach($this->sale->id);
        $url = $this->client->publicUrl();

        $this->get($url.'/shop/shirts')->assertSuccessful()->assertSee('Linen Shirt');
        $this->get($url.'/shop/gifts')->assertSuccessful()->assertSee('Linen Shirt');
        $this->get($url.'/shop/linen')->assertSuccessful()->assertDontSee('Linen Shirt');
        // The shop's category filter also finds it through a category that is not its main one.
        $this->product(['category_id' => $this->linen->id, 'name' => 'Wool Coat', 'slug' => 'wool-coat']);
        $this->get($url.'/shop?category=gifts')->assertSuccessful()->assertSee('Linen Shirt')->assertDontSee('Wool Coat');
    }
}
