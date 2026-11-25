<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\Tax;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A product can carry its HSN code, the GST classification of the goods: 4, 6 or 8 digits,
 * optional. A variant that is different goods (another material) can have its own; a variant
 * without one uses the product's.
 */
class ProductHsnCodeTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());

        $this->category = Category::create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
    }

    private function create(?string $hsn)
    {
        return Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Cotton tee', 'slug' => 'cotton-tee', 'category_level_0' => $this->category->id, 'price_excl_tax' => 500, 'hsn_code' => $hsn])
            ->call('create');
    }

    public function test_the_hsn_code_is_saved(): void
    {
        $this->create('61091000')->assertHasNoFormErrors();

        $this->assertSame('61091000', Product::withoutGlobalScopes()->where('slug', 'cotton-tee')->value('hsn_code'));
    }

    public function test_the_hsn_code_is_optional(): void
    {
        $this->create(null)->assertHasNoFormErrors();

        $this->assertNull(Product::withoutGlobalScopes()->where('slug', 'cotton-tee')->value('hsn_code'));
    }

    public function test_only_4_6_or_8_digits_are_accepted(): void
    {
        foreach (['123', '12345', '1234567', '123456789', '61AB'] as $wrong) {
            $this->create($wrong)->assertHasFormErrors(['hsn_code']);
        }
        foreach (['6109', '610910'] as $i => $right) {
            Livewire::test(CreateProduct::class)
                ->fillForm(['name' => 'Tee '.$i, 'slug' => 'tee-'.$i, 'category_level_0' => $this->category->id, 'price_excl_tax' => 500, 'hsn_code' => $right])
                ->call('create')
                ->assertHasNoFormErrors();
        }
    }

    public function test_the_hsn_code_can_be_changed_on_edit(): void
    {
        $product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $this->category->id,
            'name' => 'Linen shirt', 'slug' => 'linen-shirt', 'price' => 900, 'is_active' => true, 'hsn_code' => '6205',
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSet('data.hsn_code', '6205')
            ->fillForm(['hsn_code' => '62052000', 'category_level_0' => $this->category->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('62052000', $product->fresh()->hsn_code);
    }

    // ---- Per variant -------------------------------------------------------

    /** A shirt in cotton and polyester: different goods, so the polyester one has its own code. */
    private function shirtByMaterial(): Product
    {
        return Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $this->category->id,
            'name' => 'Oxford shirt', 'slug' => 'oxford-shirt', 'sku' => 'OXF', 'price' => 1000, 'stock' => 10,
            'is_active' => true, 'images' => ['products/shirt.jpg'], 'hsn_code' => '620520',
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Material', 'values' => ['Cotton', 'Polyester']]],
            'variants' => [
                ['options' => ['Material' => 'Cotton'], 'stock' => 5],
                ['options' => ['Material' => 'Polyester'], 'hsn_code' => '620530', 'stock' => 5],
            ],
        ]);
    }

    public function test_a_variant_uses_its_own_hsn_code_or_else_the_products(): void
    {
        $product = $this->shirtByMaterial();

        $this->assertSame('620530', $product->hsnCodeFor(['Material' => 'Polyester']));
        $this->assertSame('620520', $product->hsnCodeFor(['Material' => 'Cotton']), 'Blank falls back to the product.');
        $this->assertSame('620520', $product->hsnCodeFor(['Material' => 'Silk']), 'An unknown choice falls back to the product.');
        $this->assertSame(['620520', '620530'], array_column($product->variantList(), 'hsn_code'));
    }

    public function test_a_variants_hsn_code_is_saved_from_the_form(): void
    {
        $product = $this->shirtByMaterial();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $first = array_key_first($page->get('data.variants'));
        $page->set("data.variants.{$first}.hsn_code", '62052000')
            ->call('save')
            ->assertHasNoFormErrors();

        $variants = $product->fresh()->variants;
        $this->assertSame('62052000', $variants[0]['hsn_code']);
        $this->assertSame('620530', $variants[1]['hsn_code']);
    }

    public function test_a_variants_hsn_code_must_be_4_6_or_8_digits(): void
    {
        $product = $this->shirtByMaterial();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $first = array_key_first($page->get('data.variants'));
        $page->set("data.variants.{$first}.hsn_code", '62052')
            ->call('save')
            ->assertHasFormErrors(["variants.{$first}.hsn_code"]);
    }

    public function test_an_order_line_keeps_the_hsn_code_of_the_variant_bought(): void
    {
        $product = $this->shirtByMaterial();
        $customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Shopper', 'email' => 'shopper@alpha.test', 'password' => 'password123', 'is_active' => true]);

        foreach (['Polyester', 'Cotton'] as $material) {
            $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
                'product_id' => $product->id, 'options' => ['Material' => $material], 'qty' => 1,
            ])->assertSuccessful();
        }

        $this->actingAs($customer, 'web')
            ->withSession([Client::SESSION_KEY => 'alpha'])
            ->post('/checkout', [
                'email' => 'shopper@alpha.test', 'phone' => '9999999999', 'shipping_name' => 'Shopper',
                'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
                'shipping_postal_code' => '400001', 'shipping_country' => 'India',
                'shipping_method' => 'standard', 'payment_method' => 'cod',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $items = Order::withoutGlobalScopes()->latest('id')->firstOrFail()->items()->get()->keyBy(fn ($item) => $item->options['Material']);
        $this->assertSame('620530', $items['Polyester']->hsn_code);
        $this->assertSame('620520', $items['Cotton']->hsn_code);

        // A later change of code leaves the order as it was sold.
        $product->fresh()->update(['hsn_code' => '6205']);
        $this->assertSame('620520', $items['Cotton']->fresh()->hsn_code);
    }

    // ---- Detail page -------------------------------------------------------

    public function test_the_detail_page_shows_the_hsn_code_and_tax(): void
    {
        $tax = Tax::create(['client_id' => $this->client->id, 'name' => 'GST', 'rate' => 12]);
        $product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $this->category->id,
            'name' => 'Linen shirt', 'slug' => 'linen-shirt', 'price' => 1000, 'is_active' => true, 'hsn_code' => '62052000',
            'tax_id' => $tax->id, // prices are entered before tax: the model works out 1,120.00 with 12%
        ]);

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('HSN code')
            ->assertSee('62052000')
            ->assertSee($tax->option_label)
            ->assertSee(money(1000))
            ->assertSee(money(1120));
    }

    public function test_the_detail_page_shows_each_variants_hsn_code(): void
    {
        $product = $this->shirtByMaterial();

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('HSN 620530')
            // The cotton variant has none of its own: it shows the product's.
            ->assertSee('HSN 620520');
    }
}
