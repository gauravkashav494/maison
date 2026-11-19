<?php

namespace Tests\Feature;

use App\Clients\ClientProvisioner;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A client's subdomain and database are its own.
 *
 * A subdomain may not be a store template's slug: <slug>.<base domain> and /store/<slug> are
 * that store's theme preview, and a client there would take them over (and the reverse for a
 * store slug).
 *
 * A database belongs to exactly one client. Two clients on one database would read and
 * overwrite each other's catalogue, orders and settings, so the client form refuses a name that
 * is already taken — typed in or generated from the subdomain — and the model refuses it again
 * for any other way a client is saved.
 */
class ClientAddressAndDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    protected function setUp(): void
    {
        parent::setUp();

        config(['clients.isolation' => 'database']);
        // Only the validation is under test: no database is created for the new client.
        $this->mock(ClientProvisioner::class, fn ($mock) => $mock->shouldReceive('isNeeded')->andReturnFalse());

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function create(string $subdomain, ?string $database)
    {
        return Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Delta', 'subdomain' => $subdomain, 'database' => $database, 'storefront_id' => $this->alpha->storefront_id,
                'admin' => ['name' => 'Delta Owner', 'email' => 'owner@delta.test', 'password' => 'secret-pass1'],
            ])
            ->call('create');
    }

    public function test_a_database_another_client_uses_is_refused(): void
    {
        // Alpha has no name typed in, so it uses the generated client_alpha.
        $this->create('delta', 'client_alpha')->assertHasFormErrors(['database']);
        $this->create('delta', 'CLIENT_ALPHA')->assertHasFormErrors(['database']);

        $this->assertDatabaseMissing('clients', ['subdomain' => 'delta']);
    }

    public function test_a_generated_name_that_clashes_with_a_typed_one_is_refused(): void
    {
        $this->alpha->update(['database' => 'client_delta']);

        // Left blank, delta would resolve to client_delta, which Alpha now uses.
        $this->create('delta', null)->assertHasFormErrors(['database']);
    }

    public function test_a_deleted_clients_database_is_still_taken(): void
    {
        $this->alpha->delete(); // soft delete keeps its data, so its database stays reserved

        $this->create('delta', 'client_alpha')->assertHasFormErrors(['database']);
    }

    public function test_system_and_platform_databases_and_unsafe_names_are_refused(): void
    {
        $platform = pathinfo((string) config('database.connections.'.config('database.default').'.database'), PATHINFO_FILENAME);

        foreach (['mysql', 'information_schema', $platform, 'bad`name', '../evil', '1abc'] as $name) {
            $this->create('delta', $name)->assertHasFormErrors(['database']);
        }
    }

    public function test_a_free_name_or_the_generated_one_is_accepted(): void
    {
        $this->create('delta', null)->assertHasNoFormErrors();
        $this->assertNull(Client::where('subdomain', 'delta')->value('database'));

        $this->create('echo', ' client_echo_shop ')->assertHasNoFormErrors();
        $this->assertSame('client_echo_shop', Client::where('subdomain', 'echo')->value('database'));
    }

    public function test_a_client_can_be_saved_again_with_its_own_database(): void
    {
        $this->alpha->update(['database' => 'client_alpha', 'name' => 'Alpha Renamed']);

        $this->assertSame('client_alpha', $this->alpha->fresh()->databaseName());
    }

    public function test_the_model_refuses_a_shared_database_however_the_client_is_saved(): void
    {
        $this->expectException(ValidationException::class);

        Client::create(['name' => 'Sneaky', 'subdomain' => 'sneaky', 'database' => 'client_alpha', 'storefront_id' => $this->alpha->storefront_id, 'is_active' => true]);
    }

    public function test_a_subdomain_that_is_a_stores_own_address_is_refused(): void
    {
        // The Fashion Store's slug is "fashion": fashion.<base> opens its theme preview.
        $this->create('fashion', null)->assertHasFormErrors(['subdomain']);
        $this->assertDatabaseMissing('clients', ['subdomain' => 'fashion']);

        $this->expectException(ValidationException::class);
        Client::create(['name' => 'Sneaky', 'subdomain' => 'fashion', 'storefront_id' => $this->alpha->storefront_id, 'is_active' => true]);
    }

    public function test_an_existing_client_on_a_store_address_can_still_be_saved(): void
    {
        // Clients made before this check keep working; only a new or changed subdomain is checked.
        Client::withoutEvents(fn () => Client::whereKey($this->alpha->id)->update(['subdomain' => 'fashion']));

        $client = Client::find($this->alpha->id);
        $client->update(['name' => 'Alpha Renamed']);

        $this->assertSame('Alpha Renamed', $client->fresh()->name);
    }

    public function test_a_store_slug_that_a_client_already_uses_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        Storefront::create(['name' => 'Alpha Store', 'slug' => 'alpha', 'template' => 'fashion', 'is_active' => true]);
    }

    private function edit(Client $client, array $data)
    {
        // The edit form needs the client's admin, as every real client has one.
        ClientUser::firstOrCreate(['email' => 'admin@'.$client->subdomain.'.test'], [
            'client_id' => $client->id, 'name' => $client->name.' Admin', 'password' => 'password123',
            'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        return Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])->fillForm($data)->call('save');
    }

    private function beta(array $attributes = []): Client
    {
        return Client::create($attributes + ['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $this->alpha->storefront_id, 'is_active' => true]);
    }

    public function test_editing_a_client_refuses_another_clients_database_but_keeps_its_own(): void
    {
        $beta = $this->beta();

        $this->edit($beta, ['database' => 'client_alpha'])->assertHasFormErrors(['database']);
        $this->assertNull($beta->fresh()->database);

        // Its own (generated) name, typed in, is fine.
        $this->edit($beta, ['database' => 'client_beta'])->assertHasNoFormErrors();
        $this->assertSame('client_beta', $beta->fresh()->database);
    }

    public function test_a_custom_domain_on_the_platforms_own_domain_is_refused(): void
    {
        $base = Client::baseDomain();

        // Another client's subdomain address, a store's address, and the main domain itself.
        foreach (['alpha.'.$base, 'fashion.'.$base, $base, 'https://ALPHA.'.$base.'/'] as $host) {
            $this->edit($this->alpha, ['domain' => $host])->assertHasFormErrors(['domain']);
        }
        $this->assertNull($this->alpha->fresh()->domain);
    }

    public function test_a_custom_domain_another_client_or_store_has_is_refused(): void
    {
        $this->alpha->update(['domain' => 'shop.alpha.com']);
        Storefront::first()->update(['domain' => 'demo.fashion.com']);
        $beta = $this->beta();

        $this->edit($beta, ['domain' => 'shop.alpha.com'])->assertHasFormErrors(['domain']);
        $this->edit($beta, ['domain' => 'https://Shop.Alpha.com'])->assertHasFormErrors(['domain']);
        $this->edit($beta, ['domain' => 'demo.fashion.com'])->assertHasFormErrors(['domain']);

        // The model refuses it too, however the client is saved.
        $this->expectException(ValidationException::class);
        $beta->update(['domain' => 'SHOP.ALPHA.COM']);
    }

    public function test_a_custom_domain_is_stored_as_a_bare_host_and_a_client_keeps_its_own(): void
    {
        $this->edit($this->alpha, ['domain' => 'https://Shop.Alpha.com/'])->assertHasNoFormErrors();
        $this->assertSame('shop.alpha.com', $this->alpha->fresh()->domain);

        $this->edit($this->alpha, ['name' => 'Alpha Renamed'])->assertHasNoFormErrors();
        $this->assertSame('Alpha Renamed', $this->alpha->fresh()->name);
    }

    public function test_a_store_demo_domain_a_client_has_is_refused(): void
    {
        $this->alpha->update(['domain' => 'shop.alpha.com']);

        $this->expectException(ValidationException::class);
        Storefront::first()->update(['domain' => 'shop.alpha.com']);
    }
}
