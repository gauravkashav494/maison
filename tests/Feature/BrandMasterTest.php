<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Brands\Pages\EditBrand;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Each store keeps a brand master; products pick their brand from it and keep its name in
 * products.brand, which the shop's Brand filter and cards read.
 */
class BrandMasterTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private Client $beta;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->category = Category::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
    }

    private function signInTo(Client $client): void
    {
        $admin = ClientUser::create(['client_id' => $client->id, 'name' => 'Admin', 'email' => uniqid().'@test.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    private function brand(Client $client, string $name): Brand
    {
        return Brand::withoutGlobalScopes()->create(['client_id' => $client->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->value()]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'category_id' => $this->category->id,
            'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'price' => 2000, 'stock' => 5, 'is_active' => true,
        ]);
    }

    public function test_a_store_adds_brands_to_its_own_master_only(): void
    {
        $theirs = $this->brand($this->beta, 'Beta Only');
        $this->signInTo($this->alpha);

        Livewire::test(CreateBrand::class)
            ->fillForm(['name' => 'Acme Mills', 'slug' => 'acme-mills'])
            ->call('create')
            ->assertHasNoFormErrors();

        $mine = Brand::withoutGlobalScopes()->where('slug', 'acme-mills')->firstOrFail();
        $this->assertSame($this->alpha->id, $mine->client_id);

        // Over HTTP, where the panel scopes every query to the signed-in store.
        $this->get('/client/brands/'.$mine->getRouteKey())->assertSuccessful()->assertSee('Acme Mills');
        $this->get('/client/brands/'.$theirs->getRouteKey())->assertNotFound();
        $this->get('/client/brands/'.$theirs->getRouteKey().'/edit')->assertNotFound();
    }

    public function test_two_stores_may_each_have_a_brand_with_the_same_slug(): void
    {
        $this->brand($this->beta, 'Acme');
        $this->signInTo($this->alpha);

        Livewire::test(CreateBrand::class)
            ->fillForm(['name' => 'Acme', 'slug' => 'acme'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Brand::withoutGlobalScopes()->where('slug', 'acme')->count());
    }

    public function test_a_product_picks_its_brand_from_the_master(): void
    {
        $acme = $this->brand($this->alpha, 'Acme Mills');
        $this->signInTo($this->alpha);

        Livewire::test(CreateProduct::class)
            ->assertFormFieldVisible('brand_id')
            ->assertFormFieldHidden('brand')
            ->fillForm(['name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'categories' => [$this->category->id], 'brand_id' => $acme->id, 'price_incl_tax' => 2000])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::withoutGlobalScopes()->where('slug', 'linen-shirt')->firstOrFail();
        $this->assertSame($acme->id, $product->brand_id);
        $this->assertSame('Acme Mills', $product->brand);

        // Clearing the brand clears the name the shop reads as well.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSet('data.brand_id', $acme->id)
            ->fillForm(['brand_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($product->fresh()->brand_id);
        $this->assertNull($product->fresh()->brand);
    }

    public function test_renaming_a_brand_renames_it_on_its_products(): void
    {
        $acme = $this->brand($this->alpha, 'Acme');
        $product = $this->product(['brand_id' => $acme->id]);
        $this->assertSame('Acme', $product->fresh()->brand);
        $this->signInTo($this->alpha);

        Livewire::test(EditBrand::class, ['record' => $acme->getRouteKey()])
            ->fillForm(['name' => 'Acme Mills'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Acme Mills', $product->fresh()->brand);
    }

    public function test_writing_only_a_brand_name_links_or_creates_the_master_record(): void
    {
        $first = $this->product(['brand' => 'Acme Mills']);
        $second = $this->product(['brand' => '  acme mills ', 'slug' => 'second']);
        $other = Product::withoutGlobalScopes()->create(['client_id' => $this->beta->id, 'template' => 'fashion', 'name' => 'Coat', 'slug' => 'coat', 'price' => 10, 'brand' => 'Acme Mills']);

        $brand = Brand::withoutGlobalScopes()->where('client_id', $this->alpha->id)->where('slug', 'acme-mills')->firstOrFail();
        $this->assertSame($brand->id, $first->fresh()->brand_id);
        $this->assertSame($brand->id, $second->fresh()->brand_id);
        // The master's spelling wins on every product.
        $this->assertSame('Acme Mills', $second->fresh()->brand);
        // Another store gets its own brand of that name.
        $this->assertNotSame($brand->id, $other->fresh()->brand_id);
        $this->assertSame($this->beta->id, Brand::withoutGlobalScopes()->find($other->fresh()->brand_id)->client_id);
    }

    public function test_a_brand_deleted_for_good_leaves_its_products_without_one(): void
    {
        $acme = $this->brand($this->alpha, 'Acme');
        $product = $this->product(['brand_id' => $acme->id]);

        $acme->delete();
        $this->assertSame('Acme', $product->fresh()->brand, 'a brand in the bin can still be restored');

        $acme->forceDelete();
        $this->assertNull($product->fresh()->brand_id);
        $this->assertNull($product->fresh()->brand);
    }

    public function test_without_the_brands_module_the_brand_is_typed_in(): void
    {
        $this->alpha->update(['modules' => []]);
        $this->signInTo($this->alpha);

        Livewire::test(CreateProduct::class)
            ->assertFormFieldHidden('brand_id')
            ->assertFormFieldVisible('brand');

        $this->get('/client/brands')->assertForbidden();
    }

    public function test_the_shop_brand_filter_reads_the_masters_name(): void
    {
        $acme = $this->brand($this->alpha, 'Acme Mills');
        $this->product(['brand_id' => $acme->id]);
        $this->product(['name' => 'Wool Coat', 'slug' => 'wool-coat']);

        $this->get($this->alpha->publicUrl().'/shop?brand=Acme Mills')
            ->assertSuccessful()
            ->assertSee('Linen Shirt')
            ->assertDontSee('Wool Coat');
    }
}
