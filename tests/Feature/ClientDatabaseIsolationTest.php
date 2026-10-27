<?php

namespace Tests\Feature;

use App\Clients\ClientDatabase;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The same isolation, proven with a database per client (CLIENT_ISOLATION=database).
 *
 * The rest of the suite runs in one database, where `client_id` is what keeps the clients
 * apart. Here each client gets its own database — a file per client on sqlite, exactly as it
 * is a database per client on MySQL — and the questions are the ones that only this mode
 * raises: does a request reach the right database, does the row scope still hold inside it,
 * and is the connection put back afterwards so nothing leaks into the next request.
 *
 * Both clients run the SAME store, which is the case that used to leak.
 */
class ClientDatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private Client $beta;

    private ClientUser $alphaAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['clients.isolation' => 'database']);

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);

        $this->alpha = Client::create(['name' => 'Alpha', 'slug' => 'alpha-db', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'slug' => 'beta-db', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->alphaAdmin = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha-db.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        // The real provisioning path, without the store's seed data: a database per client
        // with the content schema in it.
        foreach ([$this->alpha, $this->beta] as $client) {
            $this->artisan('clients:provision', ['slug' => $client->slug, '--no-seed' => true])->assertSuccessful();
        }

        $this->write($this->alpha, 'Alpha Coat');
        $this->write($this->beta, 'Beta Coat');
    }

    protected function tearDown(): void
    {
        $databases = app(ClientDatabase::class);

        foreach ([$this->alpha ?? null, $this->beta ?? null] as $client) {
            if (! $client) {
                continue;
            }
            DB::purge($databases->connectionNameFor($client));
            File::delete(database_path($client->databaseName().'.sqlite'));
        }

        parent::tearDown();
    }

    /** Write a product into this client's own database, the way the panel would. */
    private function write(Client $client, string $name): Product
    {
        return app(ClientDatabase::class)->forClient($client, fn () => Product::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'template' => $client->templateId(),
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'sku' => str($name)->slug()->value(),
            'price' => 1000,
            'stock' => 5,
            'is_active' => true,
        ]));
    }

    public function test_each_client_gets_its_own_database(): void
    {
        $databases = app(ClientDatabase::class);

        $this->assertTrue($databases->isolated());
        $this->assertNotSame($this->alpha->databaseName(), $this->beta->databaseName());
        $this->assertFileExists(database_path($this->alpha->databaseName().'.sqlite'));
        $this->assertFileExists(database_path($this->beta->databaseName().'.sqlite'));

        // Platform data is not copied into them: a client database holds content only.
        $databases->forClient($this->alpha, function () use ($databases) {
            $connection = $databases->connection();
            $this->assertFalse(\Illuminate\Support\Facades\Schema::connection($connection)->hasTable('clients'));
            $this->assertFalse(\Illuminate\Support\Facades\Schema::connection($connection)->hasTable('users'));
            $this->assertTrue(\Illuminate\Support\Facades\Schema::connection($connection)->hasTable('products'));
        });
    }

    public function test_a_query_only_sees_the_selected_clients_database(): void
    {
        $databases = app(ClientDatabase::class);

        $alpha = $databases->forClient($this->alpha, fn () => Product::withoutGlobalScopes()->pluck('name')->all());
        $beta = $databases->forClient($this->beta, fn () => Product::withoutGlobalScopes()->pluck('name')->all());

        $this->assertSame(['Alpha Coat'], $alpha);
        $this->assertSame(['Beta Coat'], $beta);
    }

    public function test_the_connection_is_restored_after_serving_a_client(): void
    {
        $databases = app(ClientDatabase::class);

        $databases->use($this->alpha);
        $this->assertSame($databases->connectionNameFor($this->alpha), (new Product)->getConnectionName());

        // A nested selection does not take the previous one with it.
        $databases->forClient($this->beta, function () use ($databases) {
            $this->assertSame($databases->connectionNameFor($this->beta), (new Product)->getConnectionName());
        });
        $this->assertSame($databases->connectionNameFor($this->alpha), (new Product)->getConnectionName());

        // Even when the callback throws, the previous client comes back.
        try {
            $databases->forClient($this->beta, fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
        }
        $this->assertSame($databases->connectionNameFor($this->alpha), (new Product)->getConnectionName());

        $databases->forget();
        $this->assertNull(app(ClientDatabase::class)->connection());
        $this->assertNull((new Product)->getConnectionName());
    }

    public function test_an_id_from_another_clients_database_is_not_reachable(): void
    {
        $databases = app(ClientDatabase::class);

        // Both rows are id 1 in their own database, so the lookup has to land in the right one.
        $betaId = $databases->forClient($this->beta, fn () => Product::withoutGlobalScopes()->value('id'));

        $found = $databases->forClient($this->alpha, fn () => Product::withoutGlobalScopes()->find($betaId));

        $this->assertNotNull($found);
        $this->assertSame('Alpha Coat', $found->name);
        $this->assertSame($this->alpha->id, $found->client_id);
    }

    public function test_the_row_scope_still_applies_inside_a_clients_own_database(): void
    {
        $databases = app(ClientDatabase::class);

        // A stray row carrying another client's id — what a bad import or a copied database
        // would leave behind. The connection alone would show it; the scope must not.
        $databases->forClient($this->alpha, fn () => DB::connection($databases->connection())->table('products')->insert([
            'client_id' => $this->beta->id, 'template' => 'fashion', 'name' => 'Stray Coat',
            'slug' => 'stray-coat', 'price' => 1000, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]));

        $this->actingAs($this->alphaAdmin, 'client');
        $this->get('/client/products')
            ->assertSuccessful()
            ->assertSee('Alpha Coat')
            ->assertDontSee('Stray Coat')
            ->assertDontSee('Beta Coat');
    }

    public function test_the_client_panel_reads_the_signed_in_clients_database(): void
    {
        $this->actingAs($this->alphaAdmin, 'client');

        $this->get('/client/products')
            ->assertSuccessful()
            ->assertSee('Alpha Coat')
            ->assertDontSee('Beta Coat');

        // The parameter is ignored here too: a staff account cannot be pointed elsewhere.
        $this->get('/client/products?switch_client='.$this->beta->id)
            ->assertSuccessful()
            ->assertSee('Alpha Coat')
            ->assertDontSee('Beta Coat');
    }

    public function test_switching_client_in_the_admin_panel_switches_database(): void
    {
        $operator = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $this->actingAs($operator, 'admin');

        $this->get('/admin/products?switch_client='.$this->alpha->id)->assertRedirect('/admin/products');
        $this->get('/admin/products')->assertSuccessful()->assertSee('Alpha Coat')->assertDontSee('Beta Coat');

        $this->get('/admin/products?switch_client='.$this->beta->id)->assertRedirect('/admin/products');
        $this->get('/admin/products')->assertSuccessful()->assertSee('Beta Coat')->assertDontSee('Alpha Coat');
    }

    public function test_the_storefront_serves_the_clients_own_catalogue(): void
    {
        $this->withSession([Client::SESSION_KEY => $this->alpha->slug])
            ->get('/shop')
            ->assertSuccessful()
            ->assertSee('Alpha Coat')
            ->assertDontSee('Beta Coat');

        $this->flushSession();

        $this->withSession([Client::SESSION_KEY => $this->beta->slug])
            ->get('/shop')
            ->assertSuccessful()
            ->assertSee('Beta Coat')
            ->assertDontSee('Alpha Coat');
    }
}
