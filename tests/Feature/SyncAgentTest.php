<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\SyncAgent;
use App\Models\SyncToken;
use App\Sync\AgentPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Keeping an installed agent current: mapping and code come from the server, and code is only
 * ever handed out with a valid signature.
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
        $this->alpha = Client::create(['name' => 'Alpha', 'slug' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'slug' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
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
        $this->getJson('/api/sync/v1/agent/config')->assertUnauthorized();
        $this->getJson('/api/sync/v1/agent/package?arch=amd64')->assertUnauthorized();
        $this->getJson('/api/sync/v1/agent/package/binary?arch=amd64')->assertUnauthorized();
        $this->postJson('/api/sync/v1/agent/schema', ['tables' => []])->assertUnauthorized();
        $this->postJson('/api/sync/v1/agent/report', ['ok' => true])->assertUnauthorized();
    }

    public function test_a_mapping_change_reaches_only_that_clients_agent(): void
    {
        SyncAgent::forClient($this->beta)->update(['mapping' => ['stock' => ['table' => 'BetaStock']]]);

        $this->api('GET', 'agent/config')->assertOk()->assertJsonPath('mapping', null);

        SyncAgent::forClient($this->alpha)->update(['mapping' => ['stock' => ['table' => 'AlphaStock']]]);
        $this->api('GET', 'agent/config')->assertJsonPath('mapping.stock.table', 'AlphaStock');
    }

    public function test_the_agent_reports_its_tables_and_results(): void
    {
        $this->api('POST', 'agent/schema', ['tables' => ['Items' => [['name' => 'Code', 'type' => 'VARCHAR']]]])->assertOk();
        $this->api('POST', 'agent/report', ['agent_version' => 'abc', 'ok' => false, 'counts' => ['stock.updated' => 3], 'errors' => ['stock X: bad']])->assertOk();

        $agent = SyncAgent::where('client_id', $this->alpha->id)->first();
        $this->assertSame('Code', $agent->schema_snapshot['Items'][0]['name']);
        $this->assertSame('abc', $agent->agent_version);
        $this->assertSame(['stock X: bad'], $agent->last_report['errors']);
        $this->assertNotNull($agent->last_seen_at);
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

    public function test_a_mapping_naming_a_missing_table_or_column_is_refused(): void
    {
        SyncAgent::forClient($this->alpha)->update(['schema_snapshot' => ['Items' => [['name' => 'Code', 'type' => 'VARCHAR']]]]);
        $file = $this->keyDir.'/map.json';

        File::put($file, json_encode(['stock' => ['table' => 'Nope', 'columns' => ['sku' => 'Code']]]));
        $this->artisan('sync:agent', ['client' => 'alpha', '--map' => $file])->assertFailed();

        File::put($file, json_encode(['stock' => ['table' => 'Items', 'columns' => ['sku' => 'Nope']]]));
        $this->artisan('sync:agent', ['client' => 'alpha', '--map' => $file])->assertFailed();

        File::put($file, json_encode(['mystery' => ['table' => 'Items']]));
        $this->artisan('sync:agent', ['client' => 'alpha', '--map' => $file])->assertFailed();

        $this->assertNull(SyncAgent::forClient($this->alpha)->fresh()->mapping);

        File::put($file, json_encode(['stock' => ['table' => 'Items', 'columns' => ['sku' => 'code']]]));
        $this->artisan('sync:agent', ['client' => 'alpha', '--map' => $file])->assertSuccessful();
        $this->assertSame('Items', SyncAgent::forClient($this->alpha)->fresh()->mapping['stock']['table']);
    }
}
