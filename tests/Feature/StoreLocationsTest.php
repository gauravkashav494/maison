<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Store;
use App\Models\Storefront;
use App\Models\User;
use Database\Seeders\StoreLocationsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Store locations are each client's own: managed on /client, shown on that client's site only. */
class StoreLocationsTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private Client $beta;

    private ClientUser $alphaAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);

        // Two clients on the same store: the case where one shared list used to leak.
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->alphaAdmin = ClientUser::create(['client_id' => $this->alpha->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
    }

    private function location(Client $client, string $name): Store
    {
        return Store::withoutGlobalScopes()->create([
            'client_id' => $client->id, 'template' => $client->templateId(),
            'name' => $name, 'address' => '1 Test Street', 'city' => 'Mumbai', 'is_active' => true,
        ]);
    }

    public function test_a_client_sees_and_edits_only_its_own_locations(): void
    {
        $mine = $this->location($this->alpha, 'Alpha Boutique');
        $theirs = $this->location($this->beta, 'Beta Boutique');

        $this->actingAs($this->alphaAdmin, 'client');

        $this->get('/client/stores')->assertSuccessful()->assertSee('Alpha Boutique')->assertDontSee('Beta Boutique');
        $this->get('/client/stores/'.$mine->id.'/edit')->assertSuccessful();
        $this->get('/client/stores/'.$theirs->id.'/edit')->assertNotFound();
    }

    public function test_a_new_location_belongs_to_the_signed_in_client(): void
    {
        $this->actingAs($this->alphaAdmin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($this->alphaAdmin);
        app(\App\Admin\StoreContext::class)->resolve($this->alphaAdmin);

        Livewire::test(CreateStore::class)
            ->fillForm(['name' => 'New Shop', 'address' => '2 Test Street', 'city' => 'Pune'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stores', ['name' => 'New Shop', 'client_id' => $this->alpha->id, 'template' => 'fashion']);
    }

    public function test_the_stores_page_lists_only_the_clients_own_locations(): void
    {
        $this->location($this->alpha, 'Alpha Boutique');
        $this->location($this->beta, 'Beta Boutique');
        Page::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'storefront_template' => 'fashion', 'slug' => 'stores', 'title' => 'Visit us.', 'template' => 'stores', 'is_active' => true]);

        $this->get($this->alpha->publicUrl().'/stores')->assertSuccessful()->assertSee('Alpha Boutique')->assertDontSee('Beta Boutique');
    }

    public function test_store_locations_are_not_on_the_platform_panel(): void
    {
        $this->actingAs(User::create(['name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123', 'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true]), 'admin');

        $this->assertNotSame(200, $this->get('/stores')->getStatusCode());
    }

    public function test_each_store_template_has_sample_locations_of_its_own(): void
    {
        $names = [];
        foreach (['fashion', 'heritage', 'grocery', 'plumbing'] as $template) {
            $locations = StoreLocationsSeeder::for($template);
            $this->assertNotEmpty($locations, $template);
            $names[$template] = array_column($locations, 'name');
        }

        // No two stores share a location list (a grocery must not list clothing boutiques).
        $this->assertCount(count($names), array_unique(array_map('serialize', $names)));

        // No stores page, so no locations.
        $this->assertSame([], StoreLocationsSeeder::for('plumbing-services'));
        $this->assertSame([], StoreLocationsSeeder::for('simple'));
        $this->assertSame([], StoreLocationsSeeder::for('bulk'));
    }

    public function test_the_seeder_writes_the_locations_of_its_template_and_can_rerun(): void
    {
        app(StoreLocationsSeeder::class)->run('heritage');
        app(StoreLocationsSeeder::class)->run('heritage');

        $this->assertSame(
            array_column(StoreLocationsSeeder::for('heritage'), 'name'),
            Store::withoutGlobalScopes()->where('template', 'heritage')->orderBy('sort_order')->pluck('name')->all(),
        );
    }
}
