<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Simple List template: an image-free order list.
 *
 * It reuses the shared catalogue, cart and checkout, so these tests check what is actually
 * new — the list itself, that it carries the facts a price list needs, that a row can be
 * ordered straight from the list, and that no imagery is rendered anywhere.
 */
class SimpleTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        Storefront::syncWithTemplates();
        $store = Storefront::where('template', 'simple')->firstOrFail();

        $this->client = Client::create([
            'name' => 'Order List Demo', 'slug' => 'orderlist',
            'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true,
        ]);

        app(ClientContext::class)->resolveForPublic($this->client);
        $store->templateObject()->install();
    }

    /** Browse as a visitor who followed this client's entry link. */
    private function visit(string $path)
    {
        return $this->withSession([Client::SESSION_KEY => 'orderlist'])->get($path);
    }

    private function order(array $payload)
    {
        return $this->withSession([Client::SESSION_KEY => 'orderlist'])->postJson('/cart/items', $payload);
    }

    public function test_the_template_is_registered_and_gets_its_own_store(): void
    {
        $store = Storefront::where('template', 'simple')->firstOrFail();

        $this->assertSame('Simple List', $store->templateObject()->name());
        $this->assertFalse($store->supportsServices(), 'It is a catalogue store, so cart and checkout apply.');
        $this->assertTrue($store->templateObject()->isInstalled());
    }

    public function test_the_catalogue_is_seeded_without_any_images(): void
    {
        $products = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->get();

        $this->assertGreaterThan(20, $products->count());
        foreach ($products as $product) {
            $this->assertSame([], (array) $product->images, $product->name.' must have no images.');
            $this->assertNotEmpty($product->sku, $product->name.' needs a code for the list.');
        }
    }

    public function test_the_list_shows_the_name_code_price_stock_and_a_way_to_order(): void
    {
        $product = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->orderBy('id')->firstOrFail();

        $this->visit('/shop')
            ->assertSuccessful()
            ->assertSee($product->name)
            ->assertSee($product->sku)
            ->assertSee(number_format($product->stock).' in stock')
            ->assertSee('ol-qty', false)      // the quantity box
            ->assertSee('>Add<', false);      // the add button
    }

    public function test_the_pages_render_no_images(): void
    {
        foreach (['/', '/shop', '/cart'] as $path) {
            $html = $this->visit($path)->assertSuccessful()->getContent();
            $this->assertStringNotContainsString('<img', $html, $path.' must not render an image.');
        }
    }

    public function test_the_checkout_and_confirmation_render_no_images(): void
    {
        // These two pages used to fall through to the Fashion views, which open with a
        // photograph and draw a picture box per line — on this template every one of them is
        // empty, and the checkout's box renders as a broken image. Both now have their own
        // view, so the whole ordering flow stays image-free, not just the list and the cart.
        $product = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->orderBy('id')->firstOrFail();
        $this->order(['product_id' => $product->id, 'options' => ['Size' => $product->sizes[0]], 'qty' => 2])->assertSuccessful();

        $checkout = $this->visit('/checkout')->assertSuccessful()->getContent();
        $this->assertStringNotContainsString('<img', $checkout, '/checkout must not render an image.');

        $method = app(\App\Services\Cart::class)->shippingMethods()[0]['code'];
        $placed = $this->withSession([Client::SESSION_KEY => 'orderlist'])->post('/checkout', [
            'email' => 'ravi@example.test',
            'phone' => '9876543210',
            'shipping_name' => 'Ravi Kumar',
            'shipping_line1' => '14 Nehru Road',
            'shipping_city' => 'Pune',
            'shipping_state' => 'Maharashtra',
            'shipping_postal_code' => '411001',
            'shipping_country' => 'India',
            'shipping_method' => $method,
            'payment_method' => 'cod',
        ]);

        $placed->assertRedirect();
        $confirmation = $this->visit($placed->headers->get('Location'))->assertSuccessful()->getContent();
        $this->assertStringContainsString($product->name, $confirmation);
        $this->assertStringNotContainsString('<img', $confirmation, 'The confirmation must not render an image.');
    }

    public function test_search_narrows_the_list(): void
    {
        $match = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->where('sku', 'EL-LED-9')->firstOrFail();
        $other = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->where('sku', 'ST-A4-75')->firstOrFail();

        $this->visit('/search?q=lamp')
            ->assertSuccessful()
            ->assertSee($match->name)
            ->assertDontSee($other->name);
    }

    public function test_a_row_can_be_ordered_straight_from_the_list(): void
    {
        $product = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->where('sku', 'EL-LED-9')->firstOrFail();

        // The list posts the single pack size it fills in for the customer.
        $response = $this->order([
            'product_id' => $product->id,
            'options' => ['Size' => $product->sizes[0]],
            'qty' => 3,
        ])->assertSuccessful();

        $this->assertSame(3, $response->json('items.0.qty'));
        $this->assertSame($product->price * 3, $response->json('subtotal'));
        $this->assertSame($product->sku, $response->json('items.0.variant_sku'));
    }

    public function test_the_shared_cart_and_checkout_are_reachable(): void
    {
        $product = Product::withoutGlobalScopes()->where('client_id', $this->client->id)->orderBy('id')->firstOrFail();

        $this->order(['product_id' => $product->id, 'options' => ['Size' => $product->sizes[0]]])->assertSuccessful();

        $this->visit('/cart')->assertSuccessful()->assertSee('Your order');
        $this->visit('/checkout')->assertSuccessful();
    }
}
