<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\SyncAgent;
use App\Models\SyncToken;
use App\Models\User;
use App\Sync\AgentPackage;
use App\Sync\SyncAccess;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Keeping an installed agent current: its code comes from the server, and is only ever handed
 * out with a valid signature. The agent reaches the shop only through the shop software's API.
 */
class SyncAgentTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private Client $beta;

    private string $token;

    private string $dir;

    private string $keyDir;

    private AgentPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        [, $this->token] = SyncToken::issue($this->alpha, 'test');

        $this->dir = sys_get_temp_dir().'/agent-'.uniqid();
        $this->keyDir = sys_get_temp_dir().'/agent-key-'.uniqid();
        File::ensureDirectoryExists($this->dir);
        File::ensureDirectoryExists($this->keyDir);
        File::ensureDirectoryExists($this->dir.'/dist');
        foreach (AgentPackage::ARCHITECTURES as $arch) {
            File::put($this->dir."/dist/ecomvision-agent-{$arch}.exe", "binary for {$arch}");
        }
        $this->package = new AgentPackage($this->dir);
        $this->app->instance(AgentPackage::class, $this->package);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        File::deleteDirectory($this->keyDir);
        parent::tearDown();
    }

    private function api(string $method, string $path, array $body = []): TestResponse
    {
        return $this->withToken($this->token)->json($method, '/api/sync/v1/'.$path, $body);
    }

    private function sign(): void
    {
        $this->package->generateKey($this->keyDir.'/key.pem');
        $this->package->sign($this->keyDir.'/key.pem');
    }

    public function test_every_agent_endpoint_needs_a_token(): void
    {
        $this->getJson('/api/sync/v1/agent/package?arch=amd64')->assertUnauthorized();
        $this->getJson('/api/sync/v1/agent/package/binary?arch=amd64')->assertUnauthorized();
        $this->postJson('/api/sync/v1/agent/report', ['ok' => true])->assertUnauthorized();
    }

    public function test_the_agent_reports_its_results(): void
    {
        $this->api('POST', 'agent/report', ['agent_version' => 'abc', 'ok' => false, 'counts' => ['stock.updated' => 3], 'errors' => ['stock X: bad']])->assertOk();

        $agent = SyncAgent::where('client_id', $this->alpha->id)->first();
        $this->assertSame('abc', $agent->agent_version);
        $this->assertSame(['stock X: bad'], $agent->last_report['errors']);
        $this->assertNotNull($agent->last_seen_at);
    }

    public function test_the_agent_reads_its_own_clients_billing_software_connection(): void
    {
        // Nothing set yet: the standard address, and no key (the agent says to set it in the panel).
        $this->api('GET', 'agent/config')->assertOk()
            ->assertExactJson(['local_api_url' => SyncAgent::DEFAULT_LOCAL_API_URL, 'local_api_key' => null]);

        app(SyncAccess::class)->saveConnection($this->alpha, 'http://192.168.1.20:9000/api/', 'alpha-key');
        app(SyncAccess::class)->saveConnection($this->beta, 'http://localhost:8765/ecom/v1', 'beta-key');

        $this->api('GET', 'agent/config')
            ->assertExactJson(['local_api_url' => 'http://192.168.1.20:9000/api', 'local_api_key' => 'alpha-key']);
        // Stored encrypted, and a blank key keeps the saved one.
        $this->assertStringNotContainsString('alpha-key', (string) DB::table('sync_agents')->where('client_id', $this->alpha->id)->value('local_api_key'));
        app(SyncAccess::class)->saveConnection($this->alpha, 'http://192.168.1.20:9000/api', '');
        $this->assertSame('alpha-key', app(SyncAccess::class)->connection($this->alpha)['key']);
    }

    public function test_the_client_page_shows_where_each_clients_agent_stands(): void
    {
        $status = fn (Client $client) => app(SyncAccess::class)->status($client)['state'];

        // Alpha has a code but no agent yet; Beta nothing at all.
        $this->assertSame('waiting', $status($this->alpha));
        $this->assertSame('none', $status($this->beta));

        $this->api('POST', 'agent/report', ['agent_version' => 'abcdef1234567890', 'ok' => true, 'message' => 'Sync complete.', 'counts' => ['stock.updated' => 3]]);
        $this->assertSame('ok', $status($this->alpha));
        $this->assertSame('none', $status($this->beta));

        $this->api('POST', 'agent/report', ['ok' => false, 'message' => 'Sync stopped early.', 'errors' => ['stock X: bad']]);
        $this->assertSame('problem', $status($this->alpha));

        $this->api('POST', 'agent/report', ['ok' => true, 'message' => 'Sync complete.']);
        $this->travel(20)->minutes();
        $this->assertSame('quiet', $status($this->alpha));

        app(SyncAccess::class)->revoke($this->alpha);
        $this->assertSame('revoked', $status($this->alpha));

        // The page itself, for a platform operator.
        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])
            ->assertSee('Shop software sync')
            ->assertSee('Access revoked')
            ->assertSee('Sync complete.')
            ->assertActionHidden('revokeSync');
    }

    public function test_an_agent_gets_the_signed_build_for_its_architecture(): void
    {
        $this->sign();

        $meta = $this->api('GET', 'agent/package?arch=386')->assertOk()->assertJsonPath('current', false);
        $this->assertSame(hash('sha256', 'binary for 386'), $meta->json('version'));
        $this->assertSame(1, openssl_verify('binary for 386', base64_decode($meta->json('signature')), $this->package->publicKey(), OPENSSL_ALGO_SHA256));

        $binary = $this->withToken($this->token)->get('/api/sync/v1/agent/package/binary?arch=386')->assertOk();
        $this->assertSame('binary for 386', file_get_contents($binary->baseResponse->getFile()->getPathname()));

        $this->api('GET', 'agent/package?arch=386&have='.$this->package->version('386'))->assertExactJson(['current' => true]);
    }

    public function test_a_windows_7_agent_only_ever_gets_the_windows_7_build(): void
    {
        $this->sign();

        // It asks as amd64-win7 (update.go, buildChannel): the build made with Go 1.20, never the
        // normal one, which needs Windows 10 and would not start there.
        $meta = $this->api('GET', 'agent/package?arch=amd64-win7&have='.$this->package->version('amd64'))->assertOk();
        $this->assertSame(hash('sha256', 'binary for amd64-win7'), $meta->json('version'));
        $binary = $this->withToken($this->token)->get('/api/sync/v1/agent/package/binary?arch=amd64-win7')->assertOk();
        $this->assertSame('binary for amd64-win7', file_get_contents($binary->baseResponse->getFile()->getPathname()));

        // Each Windows has its own installer.
        $this->assertStringEndsWith('EcomVisionSyncAgent-Setup-x64-win7.exe', $this->package->installerPath('amd64-win7'));
        $this->assertStringEndsWith('EcomVisionSyncAgent-Setup-x86-win7.exe', $this->package->installerPath('386-win7'));
        $this->assertStringEndsWith('EcomVisionSyncAgent-Setup-x64.exe', $this->package->installerPath('amd64'));
        $this->assertSame('32-bit Windows 7 / 8.1', SyncAccess::windows('386-win7'));
    }

    public function test_an_unknown_architecture_is_refused(): void
    {
        $this->sign();
        $this->api('GET', 'agent/package?arch=../../.env')->assertStatus(422);
        $this->api('GET', 'agent/package/binary?arch=../../.env')->assertStatus(422);
    }

    public function test_a_build_changed_without_resigning_is_not_delivered(): void
    {
        $this->sign();
        File::append($this->dir.'/dist/ecomvision-agent-amd64.exe', 'tampered');

        $this->api('GET', 'agent/package?arch=amd64')->assertStatus(503)->assertJsonMissingPath('signature');
        $this->withToken($this->token)->getJson('/api/sync/v1/agent/package/binary?arch=amd64')->assertStatus(503);
    }

    public function test_the_signing_key_cannot_be_replaced_or_kept_in_the_project(): void
    {
        $this->sign();
        $this->artisan('sync:agent-sign', ['key' => $this->keyDir.'/key.pem', '--generate' => true])->assertFailed();
        $this->artisan('sync:agent-sign', ['key' => base_path('storage/key.pem'), '--generate' => true])->assertFailed();
        $this->assertFileDoesNotExist(base_path('storage/key.pem'));
    }

    public function test_no_setup_code_is_issued_until_the_agent_is_signed(): void
    {
        $this->artisan('sync:agent', ['client' => 'alpha', '--setup-code' => true, '--url' => 'https://shop.test'])->assertFailed();
        $this->assertSame(1, SyncToken::where('client_id', $this->alpha->id)->count());
    }

    public function test_the_setup_code_needs_https(): void
    {
        $this->sign();
        $this->artisan('sync:agent', ['client' => 'alpha', '--setup-code' => true, '--url' => 'http://shop.test'])->assertFailed();
    }

    public function test_the_setup_code_issues_a_working_token(): void
    {
        $this->sign();
        $this->artisan('sync:agent', ['client' => 'alpha', '--setup-code' => true, '--url' => 'https://shop.test'])->assertSuccessful();
        $this->assertSame(2, SyncToken::where('client_id', $this->alpha->id)->count());

        $code = AgentPackage::setupCode('https://shop.test/api/sync/v1', 'evs_abc');
        $this->assertStringStartsWith('EV1.', $code);
        $this->assertMatchesRegularExpression('/^EV1\.[A-Za-z0-9_-]+$/', $code);
        $this->assertSame("https://shop.test/api/sync/v1\nevs_abc", base64_decode(strtr(substr($code, 4), '-_', '+/')));
    }
}
