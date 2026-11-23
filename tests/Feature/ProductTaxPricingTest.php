<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Taxes\Pages\CreateTax;
use App\Filament\Resources\Taxes\Pages\EditTax;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use App\Models\Tax;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The product form asks for the MRP, the price including tax, the tax (from the tax
 * master) and the price excluding tax. Either price fills in the other at the tax's rate;
 * all of them are saved, and `price` (what the cart charges) is the one the store's prices are
 * entered as (Site settings → Payments & tax). Variants follow the product's tax.
 */
class ProductTaxPricingTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::firstOrCreate(['slug' => 'fashion'], ['name' => 'Fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Linen House', 'subdomain' => 'linen-house', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Admin', 'email' => uniqid().'@test.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());

        $this->category = Category::create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
    }

    private function taxSettings(string $mode, float $rate = 0): void
    {
        Setting::set('checkout', ['tax_mode' => $mode, 'tax_rate' => $rate] + (Setting::get('checkout') ?? []));
    }

    private function tax(string $name, float $rate): Tax
    {
        return Tax::create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name, 'rate' => $rate]);
    }

    private function variantProduct(?Tax $tax = null): Product
    {
        return Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $this->category->id,
            'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'price' => 1000, 'stock' => 5, 'is_active' => true,
            'tax_id' => $tax?->id,
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['S', 'M']]],
            'variants' => [
                ['options' => ['Size' => 'S'], 'price' => null, 'stock' => 2, 'is_active' => true],
                ['options' => ['Size' => 'M'], 'price' => 1200, 'stock' => 3, 'is_active' => true],
            ],
        ]);
    }

    public function test_a_new_product_saves_its_mrp_tax_and_both_prices(): void
    {
        $this->taxSettings('exclusive');
        $this->tax('Apparel', 5);
        $accessories = $this->tax('Accessories', 18);

        Livewire::test(CreateProduct::class)
            ->assertSet('data.tax_id', null) // no tax is picked for you
            ->fillForm(['name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'category_level_0' => $this->category->id, 'tax_id' => $accessories->id, 'price_excl_tax' => 1000, 'compare_at_price' => 1500])
            ->assertSet('data.price_incl_tax', 1180.0)
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::withoutGlobalScopes()->where('slug', 'linen-shirt')->first();
        $this->assertSame($accessories->id, $product->tax_id);
        $this->assertEquals(18, $product->tax_rate); // copied from the tax
        $this->assertEquals(1500, $product->compare_at_price);
        $this->assertEquals(1180, $product->price_incl_tax);
        $this->assertEquals(1000, $product->price_excl_tax);
        $this->assertEquals(1000, $product->price); // prices before tax: the cart charges the excluding price
    }

    public function test_the_price_including_tax_fills_in_the_price_excluding_it(): void
    {
        $this->taxSettings('inclusive');
        $apparel = $this->tax('Apparel', 5);

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'category_level_0' => $this->category->id, 'tax_id' => $apparel->id, 'price_incl_tax' => 1050])
            ->assertSet('data.price_excl_tax', 1000.0)
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::withoutGlobalScopes()->where('slug', 'linen-shirt')->first();
        $this->assertEquals(1050, $product->price); // prices include tax: the cart charges the including price
        $this->assertEquals(5, $product->tax_rate);
        $this->assertEquals(1000, $product->price_excl_tax);
    }

    public function test_a_tax_is_required_once_the_store_has_taxes(): void
    {
        $this->taxSettings('exclusive');
        $this->tax('Apparel', 5);

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'category_level_0' => $this->category->id, 'tax_id' => null, 'price_excl_tax' => 1000])
            ->call('create')
            ->assertHasFormErrors(['tax_id' => 'required']);
    }

    public function test_changing_the_class_keeps_the_saved_price_and_works_out_the_other(): void
    {
        $this->taxSettings('exclusive');
        $accessories = $this->tax('Accessories', 18);
        $apparel = $this->tax('Apparel', 12);
        $product = $this->variantProduct($accessories);

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        [$small, $medium] = array_keys($page->get('data.variants'));

        $page->assertSet('data.price_incl_tax', 1180.0)
            ->assertSet("data.variants.{$small}.price_incl_tax", null) // uses the product's price
            ->assertSet("data.variants.{$medium}.price_incl_tax", 1416.0)
            ->set('data.tax_id', $apparel->id)
            ->assertSet('data.price_excl_tax', 1000.0)
            ->assertSet('data.price_incl_tax', 1120.0)
            ->assertSet("data.variants.{$medium}.price_incl_tax", 1344.0)
            ->set("data.variants.{$small}.price_incl_tax", 1064)
            ->assertSet("data.variants.{$small}.price_excl_tax", 950.0)
            ->call('save')
            ->assertHasNoFormErrors();

        $product = $product->fresh();
        $this->assertEquals(12, $product->tax_rate);
        $this->assertEquals(1120, $product->price_incl_tax);
        $this->assertEquals(950, $product->variants[0]['price']);
        $this->assertEquals(1064, $product->variants[0]['price_incl_tax']);
        $this->assertEquals(1344, $product->variants[1]['price_incl_tax']);
        $this->assertArrayNotHasKey('tax_rate', $product->variants[1]); // variants follow the product
    }

    public function test_the_mrp_may_not_be_below_the_price_including_tax(): void
    {
        $this->taxSettings('exclusive');
        $accessories = $this->tax('Accessories', 18);

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'category_level_0' => $this->category->id, 'tax_id' => $accessories->id, 'price_excl_tax' => 1000, 'compare_at_price' => 1100])
            ->call('create')
            ->assertHasFormErrors(['compare_at_price']);
    }

    public function test_every_variant_row_shows_its_stock_and_availability(): void
    {
        $this->taxSettings('inclusive');
        $product = $this->variantProduct($this->tax('Accessories', 18));

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $first = array_key_first($page->get('data.variants'));

        // The saved price is a Hidden field: a hidden text input took a table cell, so Stock sat
        // under the wrong heading and the Available switch was not drawn at all.
        $page->assertSeeHtml('wire:model.live.blur="data.variants.'.$first.'.stock"')
            ->assertSeeHtml("\$wire.\$entangle('data.variants.{$first}.is_active'");
    }

    public function test_each_variant_row_shows_the_products_tax_read_only(): void
    {
        $this->taxSettings('exclusive');
        $accessories = $this->tax('Accessories', 18);
        $essentials = $this->tax('Essentials', 5);
        $product = $this->variantProduct($accessories);

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $first = array_key_first($page->get('data.variants'));
        $page->assertSeeHtml('placeholder="Accessories (18%)"');

        // Another tax: the column and the variants' prices follow it, and the column is not saved.
        $page->fillForm(['tax_id' => $essentials->id])
            ->assertSeeHtml('placeholder="Essentials (5%)"')
            ->assertSet("data.variants.{$first}.tax_display", null)
            ->call('save')->assertHasNoFormErrors();

        $product->refresh();
        $this->assertEquals(5, $product->tax_rate);
        $this->assertEquals(1260, $product->variants[1]['price_incl_tax']);
        $this->assertArrayNotHasKey('tax_display', $product->variants[1]);
    }

    public function test_saving_a_product_anywhere_keeps_its_rate_and_prices_in_line(): void
    {
        $this->taxSettings('inclusive');
        $accessories = $this->tax('Accessories', 18);
        $product = $this->variantProduct($accessories); // created in code, not through the form

        $this->assertEquals(18, $product->tax_rate);
        $this->assertEquals(1000, $product->price_incl_tax);
        $this->assertEquals(847.46, $product->price_excl_tax);
        $this->assertEquals(1016.95, $product->variants[1]['price_excl_tax']);
        $this->assertNull($product->variants[0]['price_incl_tax']); // uses the product's price

        // An import changes the price: both prices follow.
        $product->update(['price' => 1180]);
        $this->assertEquals(1180, $product->fresh()->price_incl_tax);
        $this->assertEquals(1000, $product->fresh()->price_excl_tax);
    }

    public function test_changing_a_classs_rate_updates_its_products(): void
    {
        $this->taxSettings('exclusive');
        $tax = $this->tax('Accessories', 18);
        $product = $this->variantProduct($tax);

        Livewire::test(EditTax::class, ['record' => $tax->getRouteKey()])
            ->fillForm(['rate' => '12'])
            ->call('save')
            ->assertHasNoFormErrors();

        $product = $product->fresh();
        $this->assertEquals(12, $product->tax_rate);
        $this->assertEquals(1000, $product->price); // the saved price stays
        $this->assertEquals(1120, $product->price_incl_tax);
        $this->assertEquals(1344, $product->variants[1]['price_incl_tax']);
    }

    public function test_a_tax_is_added_with_a_name_and_rate(): void
    {
        Livewire::test(CreateTax::class)
            ->fillForm(['name' => 'Apparel', 'rate' => '5'])
            ->call('create')
            ->assertHasNoFormErrors();

        $apparel = Tax::where('name', 'Apparel')->first();
        $this->assertEquals(5, $apparel->rate);
        $this->assertSame('Apparel (5%)', $apparel->option_label);
        $this->assertSame('GST 18%', $this->tax('GST 18%', 18)->option_label); // says its rate already
    }

    public function test_the_backfill_creates_taxes_from_the_rates_in_use_and_links_products(): void
    {
        $this->taxSettings('exclusive', 18);
        $older = $this->variantProduct();
        $zeroRated = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $this->category->id,
            'name' => 'Gift card', 'slug' => 'gift-card', 'price' => 500, 'stock' => 5, 'is_active' => true,
        ]);
        // As saved before the tax master: no tax or prices, a rate of its own on one, and a variant rate.
        $older->newQueryWithoutScopes()->whereKey($older->id)->update([
            'tax_id' => null, 'tax_rate' => null, 'price_incl_tax' => null, 'price_excl_tax' => null,
            'variants' => json_encode([['options' => ['Size' => 'M'], 'price' => 1200, 'stock' => 3, 'is_active' => true, 'tax_rate' => 5]]),
        ]);
        $zeroRated->newQueryWithoutScopes()->whereKey($zeroRated->id)->update(['tax_id' => null, 'tax_rate' => 0]);

        $this->artisan('taxes:backfill', ['subdomain' => 'linen-house'])->assertSuccessful();

        $taxes = Tax::withoutGlobalScopes()->where('client_id', $this->client->id)->orderBy('rate')->get();
        // Only the rates in use: 0% on one, the store's 18% on the one without a rate of its own.
        $this->assertSame(['No tax (0%)', 'GST 18%'], $taxes->pluck('name')->all());

        $older = $older->fresh();
        $this->assertSame($taxes[1]->id, $older->tax_id);
        $this->assertEquals(1180, $older->price_incl_tax);
        $this->assertEquals(1416, $older->variants[0]['price_incl_tax']); // at the product's rate, not the variant's 5%
        $this->assertArrayNotHasKey('tax_rate', $older->variants[0]);
        $this->assertSame($taxes[0]->id, $zeroRated->fresh()->tax_id);

        // Running it again changes nothing.
        $this->artisan('taxes:backfill', ['subdomain' => 'linen-house'])
            ->expectsOutputToContain('0 taxes added, 0 products updated')
            ->assertSuccessful();
    }

    public function test_the_taxes_screen_lists_the_stores_taxes(): void
    {
        $this->tax('Cleaning chemicals', 18);

        $this->get('/client/taxes')
            ->assertSuccessful()
            ->assertSee('Cleaning chemicals')
            ->assertSee('18%');
    }

    public function test_taxes_are_gst_slabs_and_a_rate_already_saved_still_shows(): void
    {
        Livewire::test(CreateTax::class)
            ->fillForm(['name' => 'Standard', 'rate' => '20'])
            ->call('create')
            ->assertHasFormErrors(['rate']);

        $old = $this->tax('Old VAT', 20); // saved before taxes were GST only
        Livewire::test(EditTax::class, ['record' => $old->getRouteKey()])
            ->assertFormSet(['rate' => '20'])
            ->assertSee('GST rate');
    }

    public function test_the_taxes_screen_has_no_tax_name_setting(): void
    {
        $this->get('/client/taxes')->assertSuccessful()->assertDontSee('Tax name:');
    }
}
