<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Support\Fields;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Visible in" (a row's template) is a super-admin tool: a store's staff work in their one
 * template and never see it. Moving a client to a store with another template brings their
 * content along, so it does not vanish from their site.
 */
class ClientTemplateSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Storefront $heritage;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $fashion = Storefront::firstOrCreate(['slug' => 'fashion'], ['name' => 'Fashion Store', 'template' => 'fashion', 'is_active' => true]);
        $this->heritage = Storefront::firstOrCreate(['slug' => 'heritage'], ['name' => 'Heritage Store', 'template' => 'heritage', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Acme', 'subdomain' => 'acme', 'storefront_id' => $fashion->id, 'plan_id' => $plan->id, 'is_active' => true]);
        ClientUser::create(['client_id' => $this->client->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => 'secret-pass', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
    }

    private function content(): array
    {
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
        $mine = Product::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $category->id, 'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'price' => 2000, 'stock' => 5, 'is_active' => true]);
        $shared = Product::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => null, 'category_id' => $category->id, 'name' => 'Gift Card', 'slug' => 'gift-card', 'price' => 500, 'stock' => 5, 'is_active' => true]);

        return [$category, $mine, $shared];
    }

    private function actAsSuperAdmin(): void
    {
        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_changing_the_store_moves_the_clients_content_to_the_new_template(): void
    {
        [$category, $mine, $shared] = $this->content();
        $this->actAsSuperAdmin();

        Livewire::test(EditClient::class, ['record' => $this->client->getRouteKey()])
            ->set('data.storefront_id', $this->heritage->id)
            ->assertSee('Move this client')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Store changed');

        $this->assertSame('heritage', $this->client->fresh()->templateId());
        $this->assertSame('heritage', Product::withoutGlobalScopes()->find($mine->id)->template);
        $this->assertSame('heritage', Category::withoutGlobalScopes()->find($category->id)->template);
        $this->assertNull(Product::withoutGlobalScopes()->find($shared->id)->template, 'Rows for every template stay that way.');
    }

    public function test_the_content_can_be_left_behind(): void
    {
        [, $mine] = $this->content();
        $this->actAsSuperAdmin();

        Livewire::test(EditClient::class, ['record' => $this->client->getRouteKey()])
            ->set('data.storefront_id', $this->heritage->id)
            ->set('data.move_content', false)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Store changed, content left behind');

        $this->assertSame('fashion', Product::withoutGlobalScopes()->find($mine->id)->template);
    }

    public function test_saving_without_changing_the_store_moves_nothing(): void
    {
        [, $mine] = $this->content();
        $this->actAsSuperAdmin();

        Livewire::test(EditClient::class, ['record' => $this->client->getRouteKey()])
            ->assertDontSee('Move this client')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('fashion', Product::withoutGlobalScopes()->find($mine->id)->template);
    }

    public function test_store_staff_do_not_see_visible_in_but_their_rows_get_the_stores_template(): void
    {
        $owner = ClientUser::where('email', 'owner@acme.test')->firstOrFail();
        $this->actingAs($owner, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($owner);
        app(StoreContext::class)->resolve($owner);

        $this->get('/client/products/create')->assertSuccessful()->assertDontSee('Visible in');
        $this->get('/client/categories/create')->assertSuccessful()->assertDontSee('Visible in');

        $category = Category::create(['name' => 'Kurtas', 'slug' => 'kurtas', 'is_active' => true]);
        $this->assertSame('fashion', $category->template, 'Saved with the store\'s template although the field is hidden.');
    }

    public function test_a_super_admin_still_sees_visible_in(): void
    {
        $admin = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
        app(StoreContext::class)->resolve($admin);

        $this->assertFalse(Fields::templateVisibility()->isHidden());
    }
}
