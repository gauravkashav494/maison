<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Models\Category;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
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
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
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

    public function test_stock_is_matched_by_the_local_product_id_when_the_product_has_no_sku(): void
    {
        // Made first: an API call sets the client in use for the rest of the test.
        $foreign = $this->product($this->beta, 'B1', ['external_id' => 'ITM-9']);
        $this->api('POST', 'products', ['items' => [['external_id' => 'ITM-1', 'name' => 'Soap', 'price' => 45]]])
            ->assertJsonPath('results.0.result', 'created');
        $soap = Product::query()->where('external_id', 'ITM-1')->firstOrFail();
        $this->assertNull($soap->sku);
        $tee = $this->product($this->alpha, 'TEE', ['external_id' => 'ITM-2', 'has_variants' => true, 'stock' => 0, 'variant_attributes' => ['Size'], 'variants' => [
            ['options' => ['Size' => 'M'], 'sku' => 'TEE-M', 'price' => 100, 'stock' => 0, 'is_active' => true],
        ]]);

        $response = $this->api('POST', 'stock', ['items' => [
            ['external_id' => 'ITM-1', 'on_hand' => 12, 'reason' => 'received'],
            ['external_id' => 'ITM-2', 'sku' => 'TEE-M', 'on_hand' => 4],
            ['external_id' => 'ITM-2', 'on_hand' => 9],          // sold by variant: needs the variant's sku
            ['external_id' => 'ITM-2', 'sku' => 'TEE-XL', 'on_hand' => 1],
            ['external_id' => 'ITM-9', 'on_hand' => 3],          // another client's product
            ['on_hand' => 3],                                     // names nothing
        ]]);

        $response->assertJsonPath('results.0.result', 'rejected')->assertJsonPath('results.0.key', null);
        $results = collect($response->json('results'))->slice(1)->values();
        $this->assertSame(
            [['ITM-1', 'updated'], ['TEE-M', 'updated'], ['ITM-2', 'rejected'], ['TEE-XL', 'rejected'], ['ITM-9', 'rejected']],
            $results->map(fn ($result) => [$result['key'], $result['result']])->all(),
        );
        $this->assertSame(12, (int) $soap->fresh()->stock);
        $this->assertSame(StockMovement::RECEIVED, StockMovement::query()->where('product_id', $soap->id)->latest('id')->value('reason'));
        $this->assertSame(4, (int) $tee->fresh()->stock);
        $this->assertSame(5, (int) $foreign->fresh()->stock);
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

    public function test_a_new_product_can_bring_its_category_and_variants(): void
    {
        $this->api('POST', 'products', ['items' => [[
            'external_id' => 'L1', 'sku' => 'TEE', 'name' => 'Tee', 'price' => 499, 'category' => 'T-Shirts',
            'variants' => [
                ['sku' => 'TEE-BLK-M', 'options' => ['Color' => 'Black', 'Size' => 'M']],
                ['sku' => 'TEE-BLK-L', 'options' => ['Color' => 'Black', 'Size' => 'L'], 'price' => 549],
            ],
        ]]])->assertJsonPath('results.0.result', 'created');

        $product = Product::where('sku', 'TEE')->first();
        $category = Category::find($product->category_id);
        $this->assertSame('T-Shirts', $category->name);
        $this->assertSame($this->alpha->id, (int) $category->client_id);
        $this->assertFalse($category->show_in_menu);
        $this->assertTrue($product->sellsVariants());
        $this->assertSame(['Color', 'Size'], $product->variantAttributeNames());
        // Prices come back as decimals, like every other price (money columns are decimal).
        $this->assertSame([499.0, 549.0], array_column($product->variantList(), 'price'));
        $this->assertSame('TEE-BLK-L', $product->findVariant(['Color' => 'Black', 'Size' => 'L'])['sku']);
        $this->api('GET', 'changes/products')->assertJsonCount(0, 'data');

        // Variant stock then arrives by variant SKU, and a second product in the same group reuses the category.
        $this->api('POST', 'stock', ['items' => [['sku' => 'TEE-BLK-M', 'on_hand' => 3], ['sku' => 'TEE-BLK-L', 'on_hand' => 2]]]);
        $this->assertSame(5, (int) $product->fresh()->stock);

        $this->api('POST', 'products', ['items' => [['external_id' => 'L2', 'name' => 'Polo', 'price' => 10, 'category' => 't-shirts']]]);
        $this->assertSame($category->id, (int) Product::where('external_id', 'L2')->value('category_id'));
        $this->assertSame(1, Category::where('client_id', $this->alpha->id)->count());
    }

    public function test_a_product_brings_its_categories_as_the_form_picks_them(): void
    {
        // Men already exists with Shirts under it; Women has a Shirts of its own.
        $men = Category::create(['client_id' => $this->alpha->id, 'name' => 'Men', 'slug' => 'men']);
        $shirts = Category::create(['client_id' => $this->alpha->id, 'name' => 'Shirts', 'slug' => 'men-shirts', 'parent_id' => $men->id]);
        $women = Category::create(['client_id' => $this->alpha->id, 'name' => 'Women', 'slug' => 'women']);
        Category::create(['client_id' => $this->alpha->id, 'name' => 'Shirts', 'slug' => 'women-shirts', 'parent_id' => $women->id]);
        $sale = Category::create(['client_id' => $this->alpha->id, 'name' => 'Sale', 'slug' => 'sale']);

        $results = $this->api('POST', 'products', ['items' => [
            ['external_id' => 'L1', 'name' => 'Oxford', 'price' => 999, 'category' => 'men', 'category_2' => 'Shirts', 'category_3' => 'sale', 'category_4' => 'Winter Wear'],
            ['external_id' => 'L2', 'name' => 'Linen', 'price' => 799, 'category' => 'Women', 'category_2' => 'Kurtas'],
            ['external_id' => 'L3', 'name' => 'Odd', 'price' => 10, 'category_3' => 'Sale'],   // a tag without a category
        ]])->json('results');
        $this->assertSame(['L1' => 'created', 'L2' => 'created', 'L3' => 'rejected'], collect($results)->sortBy('key')->pluck('result', 'key')->all());

        // category_2 is found under category, and is the main category; the tags are any category,
        // made at the top (out of the menu) when missing.
        $oxford = Product::where('external_id', 'L1')->first();
        $this->assertSame($shirts->id, (int) $oxford->category_id);
        $winter = Category::where('name', 'Winter Wear')->first();
        $this->assertNull($winter->parent_id);
        $this->assertFalse($winter->show_in_menu);
        $this->assertEqualsCanonicalizing([$shirts->id, $sale->id, $winter->id], $oxford->categories()->pluck('categories.id')->map(fn ($id) => (int) $id)->all());

        // A child that is missing is made under its category.
        $linen = Product::where('external_id', 'L2')->first();
        $this->assertSame(['Women', 'Kurtas'], array_map(fn ($c) => $c->name, $linen->category->lineage()));

        // Once the product has a category, the local system no longer changes it or its tags.
        $this->api('POST', 'products', ['items' => [['external_id' => 'L1', 'name' => 'Oxford', 'price' => 999, 'category' => 'Women', 'category_3' => 'Clearance']]]);
        $this->assertSame($shirts->id, (int) $oxford->fresh()->category_id);
        $this->assertSame(3, $oxford->categories()->count());

        // The feed gives them back the same way.
        $web = $this->product($this->alpha, 'W1', ['category_id' => $shirts->id]);
        $web->listUnder([$sale->id, $winter->id]);
        $row = collect($this->api('GET', 'changes/products')->json('data'))->firstWhere('id', $web->id);
        $this->assertSame(['Men', 'Shirts', 'Sale', 'Winter Wear'], [$row['category'], $row['category_2'], $row['category_3'], $row['category_4']]);
    }

    public function test_resent_variants_update_by_sku_and_keep_what_ecomvision_owns(): void
    {
        $chosen = Category::create(['client_id' => $this->alpha->id, 'name' => 'Picked on web', 'slug' => 'picked']);
        $product = $this->product($this->alpha, 'P', [
            'external_id' => 'L1', 'category_id' => $chosen->id, 'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['M'], 'swatches' => []]],
            'variants' => [['options' => ['Size' => 'M'], 'sku' => 'P-M', 'price' => 100, 'stock' => 4, 'is_active' => false, 'images' => ['m.jpg']]],
        ]);
        $this->api('POST', 'changes/products/ack', ['items' => [['id' => $product->id, 'version' => 1]]]);

        $item = ['external_id' => 'L1', 'sku' => 'P', 'name' => 'Item P', 'price' => 1000, 'category' => 'From local', 'variants' => [
            ['sku' => 'P-M', 'options' => ['size' => 'm'], 'price' => 150],
            ['sku' => 'P-L', 'options' => ['size' => 'L']],
        ]];
        $this->api('POST', 'products', ['items' => [$item]])->assertJsonPath('results.0.result', 'updated');
        $this->api('POST', 'products', ['items' => [$item]])->assertJsonPath('results.0.result', 'unchanged');

        $product->refresh();
        $this->assertSame($chosen->id, (int) $product->category_id);
        $this->assertSame(['M', 'L'], $product->variantAttributes()[0]['values']);
        $this->assertSame(['Size' => 'M'], $product->variants[0]['options']);
        $this->assertSame(150, $product->variants[0]['price']);
        $this->assertSame(4, $product->variants[0]['stock']);
        $this->assertFalse($product->variants[0]['is_active']);
        $this->assertSame(['m.jpg'], $product->variants[0]['images']);
        // A new variant also carries its prices with and without tax (Product::syncTaxPrices), unset while it has no price.
        $expected = ['sku' => 'P-L', 'options' => ['Size' => 'L'], 'price' => null, 'stock' => 0, 'is_active' => true, 'price_incl_tax' => null, 'price_excl_tax' => null];
        $actual = $product->variants[1];
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
        $this->assertFalse(Category::where('name', 'From local')->exists());
        $this->api('GET', 'changes/products')->assertJsonCount(0, 'data');
    }

    public function test_a_variant_sku_another_product_uses_is_rejected(): void
    {
        $this->product($this->alpha, 'TAKEN');
        $this->product($this->alpha, 'Q', ['has_variants' => true, 'variants' => [['options' => ['Size' => 'S'], 'sku' => 'Q-S']]]);

        $response = $this->api('POST', 'products', ['items' => [
            ['external_id' => 'L1', 'name' => 'One', 'price' => 10, 'variants' => [['sku' => 'TAKEN', 'options' => ['Size' => 'S']]]],
            ['external_id' => 'L2', 'name' => 'Two', 'price' => 10, 'variants' => [['sku' => 'Q-S', 'options' => ['Size' => 'S']]]],
            ['external_id' => 'L3', 'name' => 'Three', 'price' => 10, 'variants' => [['sku' => 'NEW-S', 'options' => ['Size' => 'S']]]],
            ['external_id' => 'L4', 'name' => 'Four', 'price' => 10, 'variants' => [['sku' => 'NEW-S', 'options' => ['Size' => 'S']]]],
            ['external_id' => 'L5', 'name' => 'Five', 'price' => 10, 'variants' => [['sku' => 'X', 'options' => []]]],
        ]]);

        $this->assertSame(['rejected', 'rejected', 'rejected', 'created', 'rejected'], array_column($response->json('results'), 'result'));
        $this->assertSame(['L5', 'L1', 'L2', 'L3', 'L4'], array_column($response->json('results'), 'key'));
    }

    public function test_a_variant_added_on_the_web_resends_the_product_but_stock_changes_do_not(): void
    {
        $product = $this->product($this->alpha, 'P', ['has_variants' => true, 'variants' => [
            ['options' => ['Size' => 'S'], 'sku' => 'P-S', 'price' => 100, 'stock' => 4, 'is_active' => true],
        ]]);
        $this->api('POST', 'changes/products/ack', ['items' => [['id' => $product->id, 'version' => 1]]]);

        // A sale or a stock push only moves stock: nothing to tell the billing software.
        $this->api('POST', 'stock', ['items' => [['sku' => 'P-S', 'on_hand' => 9]]])->assertJsonPath('results.0.result', 'updated');
        $variants = $product->fresh()->variants;
        $variants[0]['stock'] = 8;
        $variants[0]['images'] = ['s.jpg'];
        $product->fresh()->update(['variants' => $variants]);
        $this->api('GET', 'changes/products')->assertJsonCount(0, 'data');

        // A new size added on the web has to reach it.
        $variants[] = ['options' => ['Size' => 'M'], 'sku' => 'P-M', 'stock' => 0, 'is_active' => true];
        $product->fresh()->update(['variants' => $variants]);
        $this->api('GET', 'changes/products')
            ->assertJsonPath('data.0.version', 2)
            ->assertJsonPath('data.0.variants.1.sku', 'P-M');
    }

    public function test_a_product_made_on_the_web_is_offered_with_its_category_and_linked_on_ack(): void
    {
        $category = Category::create(['client_id' => $this->alpha->id, 'name' => 'Mugs', 'slug' => 'mugs']);
        $product = $this->product($this->alpha, 'MUG-02', ['category_id' => $category->id, 'compare_at_price' => 599]);

        $this->api('GET', 'changes/products')
            ->assertJsonPath('data.0.sku', 'MUG-02')
            ->assertJsonPath('data.0.category', 'Mugs')
            ->assertJsonPath('data.0.compare_at_price', 599)
            ->assertJsonPath('data.0.external_id', null);

        $this->api('POST', 'changes/products/ack', ['items' => [['id' => $product->id, 'version' => 1, 'external_id' => 'ITM-145']]]);

        $this->assertSame('ITM-145', $product->fresh()->external_id);
        $this->api('GET', 'changes/products')->assertJsonCount(0, 'data');
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

    public function test_a_local_customer_may_be_known_by_phone_alone(): void
    {
        $existing = Customer::create(['client_id' => $this->alpha->id, 'name' => 'Counter Buyer', 'phone' => '9876543210']);
        $this->api('POST', 'changes/customers/ack', ['items' => [['id' => $existing->id, 'version' => 1]]]);

        $this->api('POST', 'customers', ['items' => [
            // Matched by phone, whichever way it was typed.
            ['external_id' => 'C1', 'name' => 'Local Name', 'phone' => '+91 98765 43210'],
            ['external_id' => 'C2', 'name' => 'Phone Only', 'phone' => '091234 56789'],
            ['external_id' => 'C3', 'name' => 'Neither'],
        ]])->assertOk()
            ->assertJsonPath('results.0.key', 'C3')
            ->assertJsonPath('results.0.result', 'rejected')
            ->assertJsonPath('results.1.result', 'updated')
            ->assertJsonPath('results.2.result', 'created');

        $this->assertSame('C1', $existing->refresh()->external_id);
        $this->assertSame('Counter Buyer', $existing->name);
        $new = Customer::where('external_id', 'C2')->first();
        $this->assertNull($new->email);
        $this->assertSame('9123456789', $new->phone);

        // A phone that belongs to another local record is not taken over.
        $this->api('POST', 'customers', ['items' => [['external_id' => 'C9', 'name' => 'X', 'phone' => '9876543210']]])
            ->assertJsonPath('results.0.result', 'rejected')
            ->assertJsonPath('results.0.error', 'That phone belongs to a customer with another external_id.');
    }

    public function test_a_price_with_or_without_tax_becomes_the_stores_price(): void
    {
        // This store enters prices before tax (prices_include_tax false).
        $this->api('GET', 'ping')->assertJsonPath('prices_include_tax', false);

        $this->api('POST', 'products', ['items' => [
            // Including GST, as most shops hold it: 118 at 18% is 100 before tax.
            ['external_id' => 'T1', 'sku' => 'T1', 'name' => 'Soap', 'price_incl_tax' => 118, 'tax_rate' => 18, 'variants' => [
                ['sku' => 'T1-S', 'options' => ['Size' => 'S'], 'price_incl_tax' => 45],
                ['sku' => 'T1-L', 'options' => ['Size' => 'L'], 'price_excl_tax' => 50],
                ['sku' => 'T1-M', 'options' => ['Size' => 'M']],
            ]],
            // Before GST: saved as it is here.
            ['external_id' => 'T2', 'sku' => 'T2', 'name' => 'Oil', 'price_excl_tax' => 200, 'tax_rate' => 5],
            // `price` with another form is refused, and so is no price at all.
            ['external_id' => 'T3', 'sku' => 'T3', 'name' => 'Both', 'price' => 10, 'price_incl_tax' => 12],
            ['external_id' => 'T4', 'sku' => 'T4', 'name' => 'None'],
            // A shop keeping both prices sends both: kept as they are (38.14 × 1.18 = 45.01, within a paisa of 45).
            ['external_id' => 'T5', 'sku' => 'T5', 'name' => 'Pair', 'price_incl_tax' => 45, 'price_excl_tax' => 38.14, 'tax_rate' => 18],
            // A pair that does not agree with the tax: the store's form (before tax here) wins, the other is worked out.
            ['external_id' => 'T6', 'sku' => 'T6', 'name' => 'Odd', 'price_incl_tax' => 100, 'price_excl_tax' => 90, 'tax_rate' => 18],
        ]])->assertOk()
            ->assertJsonPath('results.0.key', 'T3')->assertJsonPath('results.0.result', 'rejected')
            ->assertJsonPath('results.1.key', 'T4')->assertJsonPath('results.1.result', 'rejected')
            ->assertJsonPath('results.2.result', 'created')
            ->assertJsonPath('results.3.result', 'created')
            ->assertJsonPath('results.4.result', 'created')
            ->assertJsonPath('results.5.result', 'created');

        $pair = Product::where('external_id', 'T5')->first();
        $this->assertSame([38.14, 45.0, 38.14], [$pair->price, $pair->price_incl_tax, $pair->price_excl_tax]);
        $odd = Product::where('external_id', 'T6')->first();
        $this->assertSame([90.0, 106.2, 90.0], [$odd->price, $odd->price_incl_tax, $odd->price_excl_tax]);

        $soap = Product::where('external_id', 'T1')->first();
        $this->assertSame([100.0, 118.0, 100.0], [$soap->price, $soap->price_incl_tax, $soap->price_excl_tax]);
        // The price the shop holds is kept to the paisa: 45 with tax stays 45, not 38.14 × 1.18 = 45.01.
        // Variant rows are JSON, so 45.0 reads back as 45: compare them as numbers.
        $prices = fn (int $i, array $keys) => array_map(fn ($key) => (float) $soap->variants[$i][$key], $keys);
        $this->assertSame([38.14, 45.0, 38.14], $prices(0, ['price', 'price_incl_tax', 'price_excl_tax']));
        $this->assertSame([50.0, 59.0], $prices(1, ['price', 'price_incl_tax']));
        $this->assertSame(100.0, $soap->priceFor(['Size' => 'M']));
        $this->assertSame([200.0, 210.0], [Product::where('external_id', 'T2')->value('price'), (float) Product::where('external_id', 'T2')->value('price_incl_tax')]);

        // Sent again the same: nothing changes.
        $this->api('POST', 'products', ['items' => [
            ['external_id' => 'T1', 'sku' => 'T1', 'name' => 'Soap', 'price_incl_tax' => 118, 'tax_rate' => 18, 'variants' => [
                ['sku' => 'T1-S', 'options' => ['Size' => 'S'], 'price_incl_tax' => 45],
                ['sku' => 'T1-L', 'options' => ['Size' => 'L'], 'price_excl_tax' => 50],
                ['sku' => 'T1-M', 'options' => ['Size' => 'M']],
            ]],
        ]])->assertJsonPath('results.0.result', 'unchanged');

        // The stock push takes a price the same ways.
        $this->api('POST', 'stock', ['items' => [['sku' => 'T1-S', 'on_hand' => 3, 'price_incl_tax' => 47.2]]])
            ->assertJsonPath('results.0.result', 'updated');
        $soap->refresh();
        $this->assertSame([40.0, 47.2], $prices(0, ['price', 'price_incl_tax']));

        // The feed gives each variant both prices as well.
        $this->api('POST', 'changes/products/ack', ['items' => [['id' => $soap->id, 'version' => $soap->sync_version]]]);
        $soap->update(['description' => 'Gentle']);
        $this->api('GET', 'changes/products')
            ->assertJsonPath('data.0.price_incl_tax', 118)
            ->assertJsonPath('data.0.variants.0.price_incl_tax', 47.2)
            ->assertJsonPath('data.0.variants.0.price_excl_tax', 40);
    }

    public function test_a_store_entering_prices_with_tax_takes_a_price_without_it(): void
    {
        Setting::mergeForClient($this->alpha, 'checkout', ['tax_mode' => 'inclusive']);
        $this->api('GET', 'ping')->assertJsonPath('prices_include_tax', true);

        $this->api('POST', 'products', ['items' => [
            ['external_id' => 'I1', 'sku' => 'I1', 'name' => 'Tee', 'price_excl_tax' => 100, 'tax_rate' => 5],
            ['external_id' => 'I2', 'sku' => 'I2', 'name' => 'Cap', 'price_incl_tax' => 299, 'tax_rate' => 12],
            // `price` is taken as the store enters it: here, including tax.
            ['external_id' => 'I3', 'sku' => 'I3', 'name' => 'Bag', 'price' => 590, 'tax_rate' => 18],
        ]])->assertJsonPath('results.2.result', 'created');

        $row = fn (string $id) => Product::where('external_id', $id)->first();
        $this->assertSame([105.0, 105.0, 100.0], [$row('I1')->price, $row('I1')->price_incl_tax, $row('I1')->price_excl_tax]);
        $this->assertSame([299.0, 299.0, 266.96], [$row('I2')->price, $row('I2')->price_incl_tax, $row('I2')->price_excl_tax]);
        $this->assertSame([590.0, 500.0], [$row('I3')->price, $row('I3')->price_excl_tax]);
    }

    public function test_hsn_codes_fill_in_while_blank_and_reach_the_local_system_with_orders(): void
    {
        $this->api('POST', 'products', ['items' => [
            ['external_id' => 'H1', 'sku' => 'TEE', 'name' => 'Tee', 'price' => 499, 'hsn_code' => '6109 10 00', 'variants' => [
                ['sku' => 'TEE-M', 'options' => ['Size' => 'M']],
                ['sku' => 'TEE-L', 'options' => ['Size' => 'L'], 'hsn_code' => '6110'],
            ]],
            ['external_id' => 'H2', 'sku' => 'BAD', 'name' => 'Bad', 'price' => 10, 'hsn_code' => '12345'],
        ]])->assertOk()
            ->assertJsonPath('results.0.key', 'H2')
            ->assertJsonPath('results.0.result', 'rejected')
            ->assertJsonPath('results.1.result', 'created');

        $product = Product::where('external_id', 'H1')->first();
        $this->assertSame('61091000', $product->hsn_code);
        $this->assertSame('61091000', $product->hsnCodeFor(['Size' => 'M']));
        $this->assertSame('6110', $product->hsnCodeFor(['Size' => 'L']));

        // Once set, the HSN codes are EcomVision's: a resend with others changes neither.
        $this->api('POST', 'products', ['items' => [
            ['external_id' => 'H1', 'sku' => 'TEE', 'name' => 'Tee', 'price' => 499, 'hsn_code' => '6205', 'variants' => [
                ['sku' => 'TEE-M', 'options' => ['Size' => 'M']],
                ['sku' => 'TEE-L', 'options' => ['Size' => 'L'], 'hsn_code' => '6206'],
            ]],
        ]])->assertJsonPath('results.0.result', 'unchanged');
        $this->assertSame('61091000', $product->refresh()->hsn_code);
        $this->assertSame('6110', $product->hsnCodeFor(['Size' => 'L']));

        // The feed carries the codes, and an order its lines' codes and the promo code's offer.
        $this->api('POST', 'changes/products/ack', ['items' => [['id' => $product->id, 'version' => $product->sync_version]]]);
        $product->update(['description' => 'Soft cotton']);
        $this->api('GET', 'changes/products')
            ->assertJsonPath('data.0.hsn_code', '61091000')
            ->assertJsonPath('data.0.variants.1.hsn_code', '6110');

        $order = $this->order($this->alpha);
        $order->update(['coupon_code' => 'SAVE10', 'coupon_type' => 'percent', 'coupon_value' => 10]);
        $order->items()->create(['product_id' => $product->id, 'name' => 'Tee (L)', 'sku' => 'TEE', 'variant_sku' => 'TEE-L', 'hsn_code' => '6110', 'price' => 499, 'qty' => 1, 'total' => 499]);
        $this->api('GET', 'changes/orders')
            ->assertJsonPath('data.0.coupon_type', 'percent')
            ->assertJsonPath('data.0.coupon_value', 10)
            ->assertJsonPath('data.0.items.0.hsn_code', '6110')
            // The line's product as the local system knows it, for its order import.
            ->assertJsonPath('data.0.items.0.product_external_id', 'H1')
            ->assertJsonPath('data.0.customer_external_id', null);
    }

    public function test_prices_carry_paise_and_brand_and_tax_fill_in_while_blank(): void
    {
        $this->api('GET', 'ping')->assertJsonPath('prices_include_tax', false);

        $this->api('POST', 'products', ['items' => [
            ['external_id' => 'P1', 'sku' => 'P1', 'name' => 'Soap', 'price' => 99.5, 'compare_at_price' => 120.25, 'brand' => 'Lux', 'tax_rate' => 18],
            ['external_id' => 'P2', 'sku' => 'P2', 'name' => 'Bad', 'price' => 9.999],
        ]])->assertOk()
            ->assertJsonPath('results.0.key', 'P2')
            ->assertJsonPath('results.0.result', 'rejected')
            ->assertJsonPath('results.1.result', 'created');

        $product = Product::where('external_id', 'P1')->first();
        $this->assertSame(99.5, $product->price);
        $this->assertSame(120.25, $product->compare_at_price);
        $this->assertSame('Lux', $product->brand);
        $this->assertNotNull($product->brand_id);
        $this->assertSame('GST 18%', $product->tax->name);
        $this->assertSame(18.0, $product->tax_rate);
        // Prices are entered before tax here, so the price with tax is worked out from it.
        $this->assertSame(99.5, $product->price_excl_tax);
        $this->assertSame(117.41, $product->price_incl_tax);

        // Once set, brand and tax are EcomVision's: a resend with others changes neither, and
        // a second product at the same rate shares the tax.
        $this->api('POST', 'products', ['items' => [
            ['external_id' => 'P1', 'sku' => 'P1', 'name' => 'Soap', 'price' => 99.5, 'compare_at_price' => 120.25, 'brand' => 'Dove', 'tax_rate' => 5],
            ['external_id' => 'P3', 'sku' => 'P3', 'name' => 'Shampoo', 'price' => 250, 'tax_rate' => '18.00'],
        ]])->assertJsonPath('results.0.result', 'unchanged');
        $this->assertSame('Lux', $product->refresh()->brand);
        $this->assertSame(18.0, $product->tax_rate);
        $this->assertSame($product->tax_id, Product::where('external_id', 'P3')->value('tax_id'));

        // Shown on the web, the product is offered with its brand, tax and categories.
        $product->update(['is_active' => true]);
        $this->api('GET', 'changes/products')
            ->assertJsonPath('data.0.brand', 'Lux')
            ->assertJsonPath('data.0.tax_rate', 18)
            ->assertJsonPath('data.0.price_incl_tax', 117.41)
            ->assertJsonPath('data.0.categories', []);
    }

    public function test_a_local_stock_count_is_recorded_in_the_stock_history(): void
    {
        $product = $this->product($this->alpha, 'S1');

        $this->api('POST', 'stock', ['items' => [['sku' => 'S1', 'on_hand' => 12, 'price' => 1099.5]]])
            ->assertJsonPath('results.0.result', 'updated');

        $this->assertSame(1099.5, $product->refresh()->price);
        $movement = StockMovement::where('product_id', $product->id)->latest('id')->first();
        $this->assertSame(StockMovement::SYNCED, $movement->reason);
        $this->assertSame(7, $movement->quantity);
        $this->assertSame(12, $movement->stock_after);
        $this->assertSame(12, (int) $product->lowest_stock);
        $this->assertSame('Sync agent', $movement->by_name);
    }

    public function test_the_local_system_says_why_stock_changed_as_on_the_adjust_stock_screen(): void
    {
        $product = $this->product($this->alpha, 'S1');
        $last = fn () => StockMovement::where('product_id', $product->id)->latest('id')->first();

        $this->api('POST', 'stock', ['items' => [['sku' => 'S1', 'on_hand' => 30, 'reason' => 'received', 'note' => 'GRN-2231']]])
            ->assertJsonPath('results.0.result', 'updated');
        $this->assertSame([StockMovement::RECEIVED, 25, 30, 'GRN-2231'], [$last()->reason, $last()->quantity, $last()->stock_after, $last()->note]);
        $this->assertSame('Stock received', $last()->reasonLabel());

        $this->api('POST', 'stock', ['items' => [['sku' => 'S1', 'on_hand' => 27, 'reason' => 'sale', 'note' => 'Bill 55012']]]);
        $this->assertSame([StockMovement::SHOP_SALE, -3, 'Sold in shop'], [$last()->reason, $last()->quantity, $last()->reasonLabel()]);

        $this->api('POST', 'stock', ['items' => [['sku' => 'S1', 'on_hand' => 26, 'reason' => 'damaged']]]);
        $this->assertSame([StockMovement::DAMAGED, -1, null], [$last()->reason, $last()->quantity, $last()->note]);

        // An unknown reason is refused, and the stock is left as it was.
        $this->api('POST', 'stock', ['items' => [['sku' => 'S1', 'on_hand' => 1, 'reason' => 'stolen']]])
            ->assertJsonPath('results.0.result', 'rejected');
        $this->assertSame(26, (int) $product->refresh()->stock);
    }

    public function test_local_fulfilment_follows_the_order_rules_and_is_not_echoed(): void
    {
        $order = $this->order($this->alpha);
        $this->api('POST', 'changes/orders/ack', ['items' => [['id' => $order->id, 'version' => 1]]]);

        // Packing is not echoed back to the local system.
        $this->api('POST', 'orders/status', ['items' => [['number' => $order->number, 'status' => 'processing']]])
            ->assertJsonPath('results.0.result', 'updated')
            ->assertJsonMissingPath('results.0.invoice_number');
        $this->api('GET', 'changes/orders')->assertJsonCount(0, 'data');

        // Dispatch issues the tax invoice: its number is new to the local system, so the order
        // comes back once, carrying it.
        $item = ['number' => $order->number, 'status' => 'shipped', 'carrier' => 'BlueDart', 'tracking_number' => 'T1'];
        $response = $this->api('POST', 'orders/status', ['items' => [$item]])->assertJsonPath('results.0.result', 'updated');

        $order->refresh();
        $this->assertSame('T1', $order->tracking_number);
        $this->assertNotNull($order->dispatched_at);
        $this->assertNotNull($order->invoice_number);
        $response->assertJsonPath('results.0.invoice_number', $order->invoice_number);

        $feed = $this->api('GET', 'changes/orders')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'shipped')
            ->assertJsonPath('data.0.invoice_number', $order->invoice_number);
        $this->api('POST', 'changes/orders/ack', ['items' => [['id' => $order->id, 'version' => $feed->json('data.0.version')]]]);

        $this->api('POST', 'orders/status', ['items' => [$item]])->assertJsonPath('results.0.result', 'unchanged');
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
            $this->artisan('clients:provision', ['subdomain' => 'alpha', '--no-seed' => true])->assertSuccessful();

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
