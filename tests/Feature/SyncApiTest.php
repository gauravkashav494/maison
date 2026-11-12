<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\SyncToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The API a client's local sync agent calls: the token decides the client, the feed resends
 * until acknowledged, and each side can only write the fields it owns.
 */
class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private Client $beta;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'slug' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'slug' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        [, $this->token] = SyncToken::issue($this->alpha, 'test');
    }

    private function api(string $method, string $path, array $body = []): TestResponse
    {
        return $this->withToken($this->token)->json($method, '/api/sync/v1/'.$path, $body);
    }

    private function product(Client $client, string $sku, array $extra = []): Product
    {
        return Product::create($extra + [
            'client_id' => $client->id, 'template' => 'fashion', 'name' => 'Item '.$sku, 'slug' => 'item-'.$sku,
            'sku' => $sku, 'price' => 1000, 'stock' => 5, 'is_active' => true,
        ]);
    }

    private function order(Client $client, ?Product $product = null, int $qty = 1, string $status = 'confirmed', ?string $variantSku = null): Order
    {
        $order = Order::create([
            'client_id' => $client->id, 'template' => 'fashion', 'number' => 'ME-'.strtoupper(uniqid()), 'email' => 's@test.test',
            'status' => $status, 'shipping_name' => 'S', 'shipping_line1' => '1 St', 'shipping_city' => 'Pune', 'shipping_state' => 'MH',
            'shipping_postal_code' => '411001', 'shipping_country' => 'India', 'subtotal' => 1000, 'total' => 1000,
        ]);
        if ($product) {
            $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'variant_sku' => $variantSku, 'price' => 1000, 'qty' => $qty, 'total' => 1000 * $qty]);
        }

        return $order;
    }

    public function test_a_missing_revoked_or_inactive_token_is_refused(): void
    {
        $this->getJson('/api/sync/v1/ping')->assertUnauthorized();
        $this->withToken('evs_wrong')->getJson('/api/sync/v1/ping')->assertUnauthorized();

        $this->api('GET', 'ping')->assertOk()->assertJsonPath('client', 'alpha');

        $this->alpha->update(['is_active' => false]);
        $this->api('GET', 'ping')->assertUnauthorized();
        $this->alpha->update(['is_active' => true]);

        $this->artisan('sync:token', ['client' => 'alpha', '--revoke' => true])->assertSuccessful();
        $this->api('GET', 'ping')->assertUnauthorized();
    }

    public function test_the_token_alone_decides_the_client(): void
    {
        $foreign = $this->product($this->beta, 'B1');
        $foreignOrder = $this->order($this->beta, $foreign);
        Customer::create(['client_id' => $this->beta->id, 'name' => 'Other', 'email' => 'other@test.test']);

        foreach (['products', 'orders', 'customers'] as $resource) {
            $this->api('GET', "changes/{$resource}?client_id={$this->beta->id}")->assertOk()->assertJsonCount(0, 'data');
        }

        $this->api('POST', 'stock', ['items' => [['sku' => 'B1', 'on_hand' => 99]]])->assertJsonPath('results.0.result', 'rejected');
        $this->api('POST', 'orders/status', ['items' => [['number' => $foreignOrder->number, 'status' => 'shipped']]])->assertJsonPath('results.0.result', 'rejected');
        $this->api('POST', 'changes/products/ack', ['items' => [['id' => $foreign->id, 'version' => 1]]])->assertJsonPath('results.0.result', 'rejected');

        $this->assertSame(5, (int) $foreign->fresh()->stock);
        $this->assertSame('confirmed', $foreignOrder->fresh()->status);
    }

    public function test_an_order_is_resent_until_acknowledged_and_again_after_a_later_change(): void
    {
        $order = $this->order($this->alpha, $this->product($this->alpha, 'A1'));

        $this->api('GET', 'changes/orders')->assertJsonPath('data.0.number', $order->number)->assertJsonPath('data.0.items.0.sku', 'A1');
        $this->api('GET', 'changes/orders')->assertJsonCount(1, 'data');

        $this->api('POST', 'changes/orders/ack', ['items' => [['id' => $order->id, 'version' => 1, 'external_id' => 'INV-77']]])->assertJsonPath('results.0.result', 'updated');
        $this->api('GET', 'changes/orders')->assertJsonCount(0, 'data');
        $this->assertSame('INV-77', $order->fresh()->external_id);

        $order->fresh()->transitionTo(Order::STATUS_CANCELLED, 'Changed mind');

        $this->api('GET', 'changes/orders')->assertJsonPath('data.0.status', 'cancelled')->assertJsonPath('data.0.version', 2);
    }

    public function test_a_stale_acknowledgement_does_not_hide_a_newer_change(): void
    {
        $order = $this->order($this->alpha);
        $order->fresh()->update(['notes' => 'Leave at gate']);

        $this->api('POST', 'changes/orders/ack', ['items' => [['id' => $order->id, 'version' => 1]]]);

        $this->api('GET', 'changes/orders')->assertJsonPath('data.0.version', 2);
        $this->api('POST', 'changes/orders/ack', ['items' => [['id' => $order->id, 'version' => 9]]])->assertJsonPath('results.0.result', 'rejected');
    }

    public function test_the_feed_pages_and_never_exposes_a_password(): void
    {
        foreach (['a', 'b', 'c'] as $name) {
            Customer::create(['client_id' => $this->alpha->id, 'name' => $name, 'email' => "{$name}@test.test", 'password' => 'secret-pass']);
        }

        $first = $this->api('GET', 'changes/customers?limit=2')->assertJsonCount(2, 'data')->assertJsonPath('has_more', true);
        $last = $first->json('data.1.id');
        $this->api('GET', "changes/customers?limit=2&after_id={$last}")->assertJsonCount(1, 'data')->assertJsonPath('has_more', false);
        $this->assertStringNotContainsString('password', $first->getContent());
    }

    public function test_a_local_product_is_created_hidden_and_not_echoed_back(): void
    {
        $this->api('POST', 'products', ['items' => [['external_id' => 'L1', 'sku' => 'S1', 'name' => 'Local Item', 'price' => 250]]])
            ->assertJsonPath('results.0.result', 'created');

        $product = Product::where('sku', 'S1')->first();
        $this->assertFalse($product->is_active);
        $this->assertSame('local-item', $product->slug);
        $this->assertSame($this->alpha->id, (int) $product->client_id);
        $this->api('GET', 'changes/products')->assertJsonCount(0, 'data');

        $this->api('POST', 'products', ['items' => [['external_id' => 'L1', 'sku' => 'S1', 'name' => 'Local Item', 'price' => 250]]])
            ->assertJsonPath('results.0.result', 'unchanged');
    }

    public function test_the_local_system_cannot_overwrite_what_ecomvision_owns(): void
    {
        $product = $this->product($this->alpha, 'A1', ['description' => 'Written on EcomVision']);

        $this->api('POST', 'changes/products/ack', ['items' => [['id' => $product->id, 'version' => 1]]]);
        $this->api('POST', 'products', ['items' => [[
            'external_id' => 'L1', 'sku' => 'A1', 'name' => 'New name', 'price' => 777,
            'description' => 'Overwritten', 'is_active' => false, 'client_id' => $this->beta->id,
        ]]])->assertJsonPath('results.0.result', 'updated');

        $product->refresh();
        $this->assertSame(777, (int) $product->price);
        $this->assertSame('New name', $product->name);
        $this->assertSame('L1', $product->external_id);
        $this->assertSame('Written on EcomVision', $product->description);
        $this->assertTrue($product->is_active);
        $this->assertSame($this->alpha->id, (int) $product->client_id);
        $this->api('GET', 'changes/products')->assertJsonCount(0, 'data');
    }

    public function test_one_bad_row_does_not_block_the_batch(): void
    {
        $response = $this->api('POST', 'products', ['items' => [
            ['external_id' => 'L1', 'name' => 'No price'],
            ['external_id' => 'L2', 'name' => 'Good', 'price' => 10],
        ]])->assertOk();

        $this->assertSame(['rejected', 'created'], array_column($response->json('results'), 'result'));
    }

    public function test_the_product_limit_applies_to_synced_products(): void
    {
        $this->alpha->update(['product_limit' => 1]);
        $this->product($this->alpha, 'A1');

        $this->api('POST', 'products', ['items' => [['external_id' => 'L1', 'name' => 'Over', 'price' => 10]]])
            ->assertJsonPath('results.0.result', 'rejected');
    }

    public function test_stock_is_held_back_for_orders_the_local_system_has_not_taken(): void
    {
        $product = $this->product($this->alpha, 'A1');
        $order = $this->order($this->alpha, $product, 3);
        $this->order($this->alpha, $product, 50, Order::STATUS_CANCELLED);

        $this->api('POST', 'stock', ['items' => [['sku' => 'A1', 'on_hand' => 10]]])->assertJsonPath('results.0.stock', 7);

        // Once taken, the local count already reflects the sale.
        $this->api('POST', 'changes/orders/ack', ['items' => [['id' => $order->id, 'version' => 1]]]);
        $this->api('POST', 'stock', ['items' => [['sku' => 'A1', 'on_hand' => 7]]])->assertJsonPath('results.0.result', 'unchanged');
        $this->assertSame(7, (int) $product->fresh()->stock);
    }

    public function test_stock_never_goes_negative_and_an_unknown_sku_is_reported(): void
    {
        $product = $this->product($this->alpha, 'A1');
        $this->order($this->alpha, $product, 9);

        $response = $this->api('POST', 'stock', ['items' => [['sku' => 'A1', 'on_hand' => 2], ['sku' => 'NOPE', 'on_hand' => 2]]]);

        $this->assertSame(0, (int) $product->fresh()->stock);
        $response->assertJsonPath('results.1.result', 'rejected');
    }

    public function test_variant_stock_and_price_update_by_variant_sku(): void
    {
        $product = $this->product($this->alpha, 'P', ['has_variants' => true, 'stock' => 8, 'variant_attributes' => ['Size'], 'variants' => [
            ['options' => ['Size' => 'S'], 'sku' => 'P-S', 'price' => 100, 'stock' => 4, 'is_active' => true],
            ['options' => ['Size' => 'M'], 'sku' => 'P-M', 'price' => 100, 'stock' => 4, 'is_active' => true],
        ]]);
        $this->order($this->alpha, $product, 1, 'confirmed', 'P-S');

        $this->api('POST', 'stock', ['items' => [
            ['sku' => 'P-S', 'on_hand' => 6, 'price' => 120],
            ['sku' => 'P', 'on_hand' => 99],
        ]])->assertJsonPath('results.1.result', 'rejected');

        $product->refresh();
        $this->assertSame(5, $product->variants[0]['stock']);
        $this->assertSame(120, $product->variants[0]['price']);
        $this->assertSame(4, $product->variants[1]['stock']);
        $this->assertSame(9, (int) $product->stock);
    }

    public function test_a_local_customer_is_added_but_an_existing_profile_is_kept(): void
    {
        $existing = Customer::create(['client_id' => $this->alpha->id, 'name' => 'Web Name', 'email' => 'web@test.test', 'password' => 'secret-pass']);
        $this->api('POST', 'changes/customers/ack', ['items' => [['id' => $existing->id, 'version' => 1]]]);

        $this->api('POST', 'customers', ['items' => [
            ['external_id' => 'C1', 'name' => 'Local Name', 'email' => 'WEB@test.test', 'phone' => '999'],
            ['external_id' => 'C2', 'name' => 'New', 'email' => 'new@test.test'],
        ]])->assertOk();

        $existing->refresh();
        $this->assertSame('Web Name', $existing->name);
        $this->assertSame('999', $existing->phone);
        $this->assertSame('C1', $existing->external_id);
        $this->assertNull(Customer::where('email', 'new@test.test')->first()->password);
        $this->api('GET', 'changes/customers')->assertJsonCount(0, 'data');
    }

    public function test_local_fulfilment_follows_the_order_rules_and_is_not_echoed(): void
    {
        $order = $this->order($this->alpha);
        $this->api('POST', 'changes/orders/ack', ['items' => [['id' => $order->id, 'version' => 1]]]);

        $item = ['number' => $order->number, 'status' => 'shipped', 'carrier' => 'BlueDart', 'tracking_number' => 'T1'];
        $this->api('POST', 'orders/status', ['items' => [$item]])->assertJsonPath('results.0.result', 'updated');
        $this->api('POST', 'orders/status', ['items' => [$item]])->assertJsonPath('results.0.result', 'unchanged');

        $order->refresh();
        $this->assertSame('T1', $order->tracking_number);
        $this->assertNotNull($order->dispatched_at);
        $this->api('GET', 'changes/orders')->assertJsonCount(0, 'data');

        $this->api('POST', 'orders/status', ['items' => [['number' => $order->number, 'status' => 'confirmed']]])
            ->assertJsonPath('results.0.result', 'rejected');
    }

    public function test_orders_from_before_sync_are_not_sent(): void
    {
        $order = $this->order($this->alpha);
        DB::table('orders')->where('id', $order->id)->update(['sync_acked_version' => 1]);

        $this->api('GET', 'changes/orders')->assertJsonCount(0, 'data');
    }

    public function test_it_works_against_the_clients_own_database(): void
    {
        config(['clients.isolation' => 'database']);
        $databases = app(ClientDatabase::class);

        try {
            $this->artisan('clients:provision', ['slug' => 'alpha', '--no-seed' => true])->assertSuccessful();

            $this->api('POST', 'products', ['items' => [['external_id' => 'L1', 'sku' => 'S1', 'name' => 'Own DB', 'price' => 5]]])
                ->assertJsonPath('results.0.result', 'created');
            $this->api('POST', 'stock', ['items' => [['sku' => 'S1', 'on_hand' => 4]]])->assertJsonPath('results.0.stock', 4);

            $this->assertSame(0, DB::table('products')->where('sku', 'S1')->count());
            $this->assertSame(1, DB::connection($databases->connectionNameFor($this->alpha))->table('products')->where('sku', 'S1')->count());
        } finally {
            app(ClientContext::class)->forget();
            $databases->forget();
            DB::purge($databases->connectionNameFor($this->alpha));
            File::delete(database_path($this->alpha->databaseName().'.sqlite'));
        }
    }
}
