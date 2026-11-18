<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Support\SkuGenerator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A Bulk Order store needs a code on every product (buyers order by it), so its product form
 * requires the SKU and fills in the next free one. Other stores keep it optional and blank.
 */
class ProductSkuTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $template, string $name): Client
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::firstOrCreate(['slug' => $template], ['name' => ucfirst($template), 'template' => $template, 'is_active' => true]);

        return Client::create(['name' => $name, 'subdomain' => Str::slug($name), 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function signInTo(Client $client): void
    {
        $admin = ClientUser::create([
            'client_id' => $client->id, 'name' => 'Admin', 'email' => uniqid().'@test.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    private function product(Client $client, string $template, ?string $sku, string $slug): Product
    {
        return Product::withoutGlobalScopes()->create([
            'client_id' => $client->id, 'template' => $template, 'name' => ucfirst($slug), 'slug' => $slug, 'sku' => $sku, 'price' => 100, 'is_active' => true,
        ]);
    }

    public function test_a_bulk_store_gets_the_next_code_in_its_own_pattern(): void
    {
        $client = $this->client('bulk', 'Trade Supplies');
        $this->product($client, 'bulk', 'SU-1001', 'one');
        $this->product($client, 'bulk', 'SU-1012', 'two');
        $this->signInTo($client);

        Livewire::test(CreateProduct::class)->assertSet('data.sku', 'SU-1013');
    }

    public function test_a_bulk_store_without_codes_starts_from_its_initials(): void
    {
        $this->signInTo($this->client('bulk', 'Trade Supplies'));

        $this->assertSame('TS-1001', SkuGenerator::next());
    }

    public function test_the_code_is_optional_on_a_bulk_store_and_can_be_changed(): void
    {
        $client = $this->client('bulk', 'Trade Supplies');
        $this->signInTo($client);
        $category = Category::create(['client_id' => $client->id, 'template' => 'bulk', 'name' => 'Cleaning', 'slug' => 'cleaning', 'is_active' => true]);

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Hand wash', 'slug' => 'hand-wash', 'categories' => [$category->id], 'price_excl_tax' => 120, 'sku' => ''])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertNull(Product::withoutGlobalScopes()->where('slug', 'hand-wash')->value('sku'));

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Floor cleaner', 'slug' => 'floor-cleaner', 'categories' => [$category->id], 'price_excl_tax' => 250, 'sku' => 'FLOOR-5L'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('FLOOR-5L', Product::withoutGlobalScopes()->where('slug', 'floor-cleaner')->value('sku'));
    }

    public function test_a_bulk_product_saved_without_a_code_stays_blank_on_edit(): void
    {
        $client = $this->client('bulk', 'Trade Supplies');
        $this->product($client, 'bulk', 'SU-1001', 'one');
        $blank = $this->product($client, 'bulk', null, 'blank');
        $this->signInTo($client);

        $category = Category::create(['client_id' => $client->id, 'template' => 'bulk', 'name' => 'Cleaning', 'slug' => 'cleaning', 'is_active' => true]);

        Livewire::test(EditProduct::class, ['record' => $blank->getRouteKey()])
            ->assertSet('data.sku', null)
            ->fillForm(['categories' => [$category->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($blank->fresh()->sku);
    }

    public function test_other_stores_keep_the_code_optional_and_blank(): void
    {
        $client = $this->client('fashion', 'Alpha');
        $this->product($client, 'fashion', 'AL-1001', 'one');
        $this->signInTo($client);

        Livewire::test(CreateProduct::class)->assertSet('data.sku', null);
    }
}
