<?php

namespace Tests\Feature;

use App\Clients\ClientDatabase;
use App\Clients\ClientProvisioner;
use App\Clients\ClientQuota;
use App\Clients\ClientSetup;
use App\Clients\ClientTotals;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\Clients\RelationManagers\TeamRelationManager;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
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

    /** @var array<Client> clients a test provisioned itself */
    private array $extraClients = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['clients.isolation' => 'database', 'clients.prefix' => self::filePrefix().getmypid().'_']);
        self::removeAbandonedFiles();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);

        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha-db', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta-db', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->alphaAdmin = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha-db.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        // The real provisioning path, without the store's seed data: a database per client
        // with the content schema in it.
        foreach ([$this->alpha, $this->beta] as $client) {
            $this->artisan('clients:provision', ['subdomain' => $client->subdomain, '--no-seed' => true])->assertSuccessful();
        }

        $this->write($this->alpha, 'Alpha Coat');
        $this->write($this->beta, 'Beta Coat');
    }

    protected function tearDown(): void
    {
        $databases = app(ClientDatabase::class);

        foreach ([$this->alpha ?? null, $this->beta ?? null, ...$this->extraClients] as $client) {
            if (! $client) {
                continue;
            }
            DB::purge($databases->connectionNameFor($client));
            File::delete(database_path($client->databaseName().'.sqlite'));
        }

        parent::tearDown();
    }

    /** Write a product into this client's own database, the way the panel would. */
    /** Each run's files start with this and its process id, so two runs at once never share one. */
    private static function filePrefix(): string
    {
        return 'client_test_';
    }

    /**
     * Deletes files left by a run that stopped before tearDown (killed, crashed or timed out):
     * any whose process is no longer running.
     */
    private static function removeAbandonedFiles(): void
    {
        foreach (File::glob(database_path(self::filePrefix().'*.sqlite')) as $file) {
            $pid = (int) Str::between(basename($file), self::filePrefix(), '_');
            if ($pid !== getmypid() && ! file_exists("/proc/{$pid}") && ! (function_exists('posix_kill') && posix_kill($pid, 0))) {
                File::delete($file);
            }
        }
    }

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
            $this->assertFalse(Schema::connection($connection)->hasTable('clients'));
            $this->assertFalse(Schema::connection($connection)->hasTable('users'));
            $this->assertTrue(Schema::connection($connection)->hasTable('products'));
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

    public function test_the_operators_totals_read_each_clients_own_database(): void
    {
        $operator = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $this->actingAs($operator, 'admin');

        $this->get('/client-totals')->assertSuccessful()->assertDontSee('Alpha Coat');

        $totals = ClientTotals::all()->keyBy(fn (array $row) => $row['client']->subdomain);
        $this->assertSame(1, $totals['alpha-db']['products']);
        $this->assertSame(1, $totals['beta-db']['products']);
    }

    private function operator(): User
    {
        return User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
    }

    public function test_a_client_without_a_database_reads_as_empty(): void
    {
        $client = Client::create(['name' => 'Gamma', 'subdomain' => 'gamma-db', 'storefront_id' => $this->alpha->storefront_id, 'plan_id' => $this->alpha->plan_id, 'is_active' => true]);

        $quota = ClientQuota::for($client);
        $this->assertSame(0, $quota->productCount());
        $this->assertSame(0, $quota->imageCount());
        $this->assertSame([], $quota->imagePaths());
        $this->assertSame(0, ClientTotals::all()->keyBy(fn (array $row) => $row['client']->subdomain)['gamma-db']['products']);

        // The pages that offer the retry have to open for that client.
        $this->actingAs($this->operator(), 'admin');
        $this->get('/clients')->assertSuccessful()->assertSee('Gamma');
        $this->get('/clients/'.$client->getRouteKey().'/edit')->assertSuccessful();
    }

    public function test_a_client_with_an_empty_database_reads_as_empty(): void
    {
        $databases = app(ClientDatabase::class);
        $databases->forClient($this->alpha, fn () => Schema::connection($databases->connection())->drop('products'));

        $this->assertSame(0, ClientQuota::for($this->alpha)->productCount());
        $this->assertSame([], ClientQuota::for($this->alpha)->imagePaths());
    }

    public function test_other_query_errors_are_not_hidden(): void
    {
        $databases = app(ClientDatabase::class);

        $this->expectException(QueryException::class);

        $databases->forClientOr($this->alpha, fn () => DB::connection($databases->connection())->select('select no_such_function()'), 0);
    }

    public function test_a_client_can_be_created_before_its_database_exists(): void
    {
        // Stops at the point the real flow reaches first: saved, nothing provisioned yet.
        $this->mock(ClientProvisioner::class, fn ($mock) => $mock->shouldReceive('isNeeded')->andReturnFalse());

        $this->actingAs($this->operator(), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Delta', 'subdomain' => 'delta-db', 'storefront_id' => $this->alpha->storefront_id,
                'admin' => ['name' => 'Delta Owner', 'email' => 'owner@delta.test', 'password' => 'secret-pass1'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('clients', ['subdomain' => 'delta-db']);
        $this->assertDatabaseHas('client_users', ['email' => 'owner@delta.test', 'role' => ClientUser::ROLE_CLIENT_ADMIN]);
    }

    public static function stores(): array
    {
        return array_combine($ids = ['fashion', 'grocery', 'heritage', 'plumbing', 'plumbing-services', 'bulk'], array_map(fn ($id) => [$id], $ids));
    }

    #[DataProvider('stores')]
    public function test_a_store_without_samples_has_no_products_and_still_renders(string $template): void
    {
        $store = Storefront::firstOrCreate(['template' => $template], ['name' => $template, 'slug' => $template, 'is_active' => true]);
        $client = Client::create(['name' => 'Gamma', 'subdomain' => 'gamma-db', 'storefront_id' => $store->id, 'plan_id' => $this->alpha->plan_id, 'is_active' => true]);
        $this->extraClients[] = $client;

        $this->artisan('clients:provision', ['subdomain' => $client->subdomain, '--no-samples' => true])->assertSuccessful();

        $database = DB::connection(app(ClientDatabase::class)->connectionNameFor($client));
        $this->assertSame(0, $database->table('products')->count());
        $this->assertSame(0, $database->table('categories')->count());
        $this->assertSame(0, $database->table('collections')->count());
        $this->assertSame(0, $database->table('collection_product')->count());
        foreach (['services', 'service_areas', 'projects', 'testimonials'] as $table) {
            $this->assertSame(0, $database->table($table)->count(), $table);
        }
        $this->assertGreaterThan(0, $database->table('menus')->count());

        foreach (['/', '/shop', '/collections'] as $path) {
            $this->flushSession();
            $this->assertLessThan(500, $this->withSession([Client::SESSION_KEY => $client->subdomain])->get($path)->getStatusCode(), $path);
        }

        // No menu may be left pointing at sample content that was removed, or at a broken page.
        foreach ($database->table('menu_items')->where('url', 'like', '/%')->pluck('url')->unique() as $url) {
            $this->flushSession();
            $status = $this->withSession([Client::SESSION_KEY => $client->subdomain])->get($url)->getStatusCode();
            $this->assertLessThan(500, $status, $url);
            if (preg_match('#^/(shop|collections|services|service-areas)/#', $url)) {
                $this->assertNotSame(404, $status, $url);
            }
        }

        if ($template === 'plumbing-services') {
            $this->flushSession();
            $this->withSession([Client::SESSION_KEY => $client->subdomain])->get('/services/no-such-service')->assertRedirect(route('services.index'));
            $this->flushSession();
            $this->withSession([Client::SESSION_KEY => $client->subdomain])->get('/service-areas/no-such-area')->assertRedirect(route('areas.index'));
        }
    }

    public function test_removing_samples_never_touches_existing_categories(): void
    {
        $databases = app(ClientDatabase::class);
        $databases->forClient($this->alpha, fn () => DB::connection($databases->connection())->table('categories')->insert([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Real Category', 'slug' => 'real-category',
            'created_at' => now(), 'updated_at' => now(),
        ]));
        $databases->forClient($this->alpha, fn () => DB::connection($databases->connection())->table('products')->delete());

        $this->artisan('clients:provision', ['subdomain' => $this->alpha->subdomain, '--no-samples' => true])->assertSuccessful();

        $this->assertTrue(DB::connection($databases->connectionNameFor($this->alpha))->table('categories')->where('slug', 'real-category')->exists());
    }

    public function test_removing_samples_never_touches_a_catalogue_that_already_has_products(): void
    {
        $this->artisan('clients:provision', ['subdomain' => $this->alpha->subdomain, '--no-samples' => true])->assertSuccessful();

        $names = app(ClientDatabase::class)->forClient($this->alpha, fn () => Product::withoutGlobalScopes()->pluck('name')->all());
        $this->assertContains('Alpha Coat', $names);
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_the_create_form_passes_on_the_sample_products_choice(bool $choice): void
    {
        $this->mock(ClientProvisioner::class, function ($mock) use ($choice) {
            $mock->shouldReceive('isNeeded')->andReturnTrue();
            $mock->shouldReceive('provision')->once()->withArgs(fn ($client, $samples) => $samples === $choice)->andReturn(['ok' => true, 'message' => 'ready']);
        });

        $this->actingAs($this->operator(), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Delta', 'subdomain' => 'delta-db', 'storefront_id' => $this->alpha->storefront_id, 'sample_products' => $choice,
                'admin' => ['name' => 'Delta Owner', 'email' => 'owner@delta.test', 'password' => 'secret-pass1'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('clients', ['subdomain' => 'delta-db']);
    }

    public function test_the_retry_offers_the_sample_products_choice_when_there_is_no_database(): void
    {
        $this->mock(ClientProvisioner::class, function ($mock) {
            $mock->shouldReceive('isNeeded')->andReturnTrue();
            $mock->shouldReceive('databaseExists')->andReturnFalse();
            $mock->shouldReceive('repairReason')->andReturn('missing');
            $mock->shouldReceive('provision')->once()->withArgs(fn ($client, $samples) => $samples === true)->andReturn(['ok' => true, 'message' => 'ready']);
        });

        $this->actingAs($this->operator(), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])
            ->assertActionVisible('repairDatabase')
            ->callAction('repairDatabase', ['sample_products' => true])
            ->assertHasNoActionErrors();
    }

    public function test_repair_is_offered_only_when_the_database_needs_it(): void
    {
        $this->artisan('clients:provision', ['subdomain' => $this->alpha->subdomain, '--no-seed' => true])->assertSuccessful();
        $provisioner = app(ClientProvisioner::class);
        $this->assertNull($provisioner->repairReason($this->alpha), 'A fully built database needs no repair');

        $this->actingAs($this->operator(), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])
            ->assertActionHidden('repairDatabase');

        // A client migration added after the database was built makes it "outdated".
        $databases = app(ClientDatabase::class);
        $databases->forClient($this->alpha, fn () => DB::connection($databases->connection())->table('migrations')->where('migration', '0001_01_01_000003_add_soft_deletes')->delete());
        $this->assertSame('outdated', $provisioner->repairReason($this->alpha));
    }

    public function test_the_client_page_lists_its_team_in_a_filterable_table(): void
    {
        $owner = ClientUser::create(['client_id' => $this->alpha->id, 'name' => 'Alpha Owner', 'email' => 'owner@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $away = ClientUser::create(['client_id' => $this->alpha->id, 'name' => 'Alpha Away', 'email' => 'away@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'is_active' => false]);

        $this->actingAs($this->operator(), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get('/clients/'.$this->alpha->getRouteKey())->assertSuccessful()->assertSee('Team');

        Livewire::test(TeamRelationManager::class, [
            'ownerRecord' => $this->alpha,
            'pageClass' => ViewClient::class,
        ])
            ->assertCanSeeTableRecords([$owner, $away])
            ->assertSee($this->alpha->users()->count().' staff account(s), '.$this->alpha->customers()->count().' customer(s).')
            ->filterTable('is_active', true)->assertCanSeeTableRecords([$owner])->assertCanNotSeeTableRecords([$away])
            ->resetTableFilters()
            ->filterTable('role', ClientUser::ROLE_ADMIN_STAFF)->assertCanSeeTableRecords([$away])->assertCanNotSeeTableRecords([$owner])
            ->resetTableFilters()
            ->searchTable('owner@')->assertCanSeeTableRecords([$owner])->assertCanNotSeeTableRecords([$away]);
    }

    public function test_a_missing_category_or_collection_redirects_instead_of_a_404(): void
    {
        $databases = app(ClientDatabase::class);
        $databases->forClient($this->alpha, fn () => DB::connection($databases->connection())->table('categories')->insert([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Real Category', 'slug' => 'real-category',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]));

        $session = [Client::SESSION_KEY => $this->alpha->subdomain];

        $this->withSession($session)->get('/shop/no-such-category')->assertRedirect(route('shop.index'));
        $this->withSession($session)->get('/collections/no-such-collection')->assertRedirect(route('collections.index'));

        // Pages that do exist are untouched.
        $this->withSession($session)->get('/shop/real-category')->assertSuccessful();
        $this->withSession($session)->get('/shop/sale')->assertSuccessful();
    }

    public function test_clients_on_the_same_store_do_not_share_cached_menus(): void
    {
        $databases = app(ClientDatabase::class);

        $databases->forClient($this->alpha, function () {
            $menu = Menu::create(['location' => 'header', 'name' => 'Header', 'client_id' => $this->alpha->id]);
            $menu->allItems()->create(['label' => 'Alpha Link', 'url' => '/alpha', 'client_id' => $this->alpha->id]);
        });

        $alpha = $databases->forClient($this->alpha, fn () => Menu::tree('header')->pluck('label')->all());
        $beta = $databases->forClient($this->beta, fn () => Menu::tree('header')->pluck('label')->all());

        $this->assertSame(['Alpha Link'], $alpha);
        $this->assertSame([], $beta);
    }

    public function test_seeding_a_fashion_client_keeps_the_stores_shared_settings(): void
    {
        Setting::query()->whereIn('key', ['site', 'seo', 'home'])->delete();
        Setting::set('home', ['hero_eyebrow' => 'Edited by the operator']);

        $databases = app(ClientDatabase::class);
        $databases->forClient($this->alpha, fn () => app(SettingsSeeder::class)->run());

        $this->assertSame(['hero_eyebrow' => 'Edited by the operator'], Setting::get('home'));

        // A group that was never saved still gets its defaults.
        $this->assertSame('Maison Élan', Setting::get('site.name'));
    }

    public function test_the_storefront_serves_the_clients_own_catalogue(): void
    {
        $this->withSession([Client::SESSION_KEY => $this->alpha->subdomain])
            ->get('/shop')
            ->assertSuccessful()
            ->assertSee('Alpha Coat')
            ->assertDontSee('Beta Coat');

        $this->flushSession();

        $this->withSession([Client::SESSION_KEY => $this->beta->subdomain])
            ->get('/shop')
            ->assertSuccessful()
            ->assertSee('Beta Coat')
            ->assertDontSee('Alpha Coat');
    }

    /** Read one product straight from a client's database file, as stored. */
    private function storedProduct(Client $client, string $name): ?object
    {
        return app(ClientDatabase::class)->forClient($client, fn () => DB::connection(app(ClientDatabase::class)->connection())
            ->table('products')->where('name', $name)->first());
    }

    public function test_a_new_database_name_moves_the_database_with_every_row_as_it_was(): void
    {
        $before = $this->storedProduct($this->alpha, 'Alpha Coat');
        $oldFile = database_path($this->alpha->databaseName().'.sqlite');
        $newName = self::filePrefix().getmypid().'_alpha_renamed';

        $this->alpha->update(['database' => $newName]);

        // The same database under its new name: nothing created empty, nothing left behind.
        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists(database_path($newName.'.sqlite'));

        // Same row, same id, same dates — moved, not copied or re-saved.
        $after = $this->storedProduct($this->alpha->fresh(), 'Alpha Coat');
        $this->assertNotNull($after);
        $this->assertEquals((array) $before, (array) $after);

        // The other client is untouched.
        $this->assertNotNull($this->storedProduct($this->beta, 'Beta Coat'));
    }

    public function test_a_new_subdomain_moves_a_generated_database_name_too(): void
    {
        $oldFile = database_path($this->alpha->databaseName().'.sqlite');

        $this->alpha->update(['subdomain' => 'alpha-new']);

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists(database_path($this->alpha->databaseName().'.sqlite'));
        $this->assertNotNull($this->storedProduct($this->alpha->fresh(), 'Alpha Coat'));
    }

    public function test_a_name_that_already_exists_is_used_as_it_is_and_nothing_moves(): void
    {
        $existing = self::filePrefix().getmypid().'_existing';
        touch(database_path($existing.'.sqlite'));
        $oldFile = database_path($this->alpha->databaseName().'.sqlite');

        try {
            $this->alpha->update(['database' => $existing]);

            // The client now reads the database that was there; its old one is left alone.
            $this->assertFileExists($oldFile);
            $this->assertSame(0, filesize(database_path($existing.'.sqlite')));
        } finally {
            File::delete($oldFile);
        }
    }

    public function test_a_database_name_is_not_moved_while_its_setup_is_running(): void
    {
        $oldFile = database_path($this->alpha->databaseName().'.sqlite');
        Cache::forever('client-setup:'.$this->alpha->id, ['state' => ClientSetup::RUNNING]); // as ClientSetup stores it

        try {
            $this->alpha->update(['database' => self::filePrefix().getmypid().'_too_soon']);
            $this->fail('The rename should have been refused.');
        } catch (ValidationException) {
            $this->alpha->refresh(); // drop the refused name, so tearDown removes the real file
            $this->assertFileExists($oldFile);
            $this->assertNull($this->alpha->database);
        }
    }

    public function test_saving_a_new_database_name_on_the_edit_page_says_the_database_moved(): void
    {
        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $newName = self::filePrefix().getmypid().'_alpha_edited';

        Livewire::test(EditClient::class, ['record' => $this->alpha->getRouteKey()])
            ->fillForm(['database' => $newName])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Database renamed');

        $this->alpha->refresh();
        $this->assertSame($newName, $this->alpha->database);
        $this->assertNotNull($this->storedProduct($this->alpha, 'Alpha Coat'));
    }
}
