<?php

namespace Tests\Feature;

use App\Http\Controllers\Storefront\CartController;
use App\Models\Client;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A product hidden, deleted or with its variant switched off after it was added stays in the cart,
 * listed apart as no longer available and out of the totals, until the shopper removes it; no
 * order is placed while one is left. The shopper is told once in a toast.
 */
class CartUnavailableTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private const CHECKOUT = [
        'email' => 'buyer@alpha.test', 'phone' => '9999999999', 'shipping_name' => 'Buyer',
        'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
        'shipping_postal_code' => '400001', 'shipping_country' => 'India',
        'shipping_method' => 'standard', 'payment_method' => 'cod',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function onStore(): static
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha']);
    }

    private function product(string $name, array $attributes = [], string $template = 'fashion'): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->client->id, 'template' => $template, 'name' => $name, 'slug' => str($name)->slug()->toString(),
            'sku' => strtoupper(substr($name, 0, 3)).'-1', 'price' => 500, 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function add(Product $product, array $options = []): void
    {
        $this->onStore()->postJson('/cart/items', ['product_id' => $product->id, 'options' => $options])->assertSuccessful();
    }

    public function test_a_hidden_product_stays_in_the_cart_marked_unavailable_and_out_of_the_totals(): void
    {
        $container = $this->product('Food container');
        $bottle = $this->product('Water bottle');
        $this->add($container);
        $this->add($bottle);

        $container->update(['is_active' => false]);

        $cart = $this->onStore()->getJson('/cart/items')->assertSuccessful()->json();
        $this->assertSame(['Water bottle'], array_column(array_column($cart['items'], 'product'), 'name'));
        $this->assertSame(['Food container'], array_column($cart['unavailable'], 'name'));
        $this->assertEquals(500, $cart['subtotal']);
        $this->assertSame(1, $cart['count']);
        $this->assertSame('Food container is no longer available. Please remove it from your cart.', $cart['unavailable_toast']);

        // Still listed on the next load, but the toast is said once.
        $cart = $this->onStore()->getJson('/cart/items')->json();
        $this->assertCount(1, $cart['unavailable']);
        $this->assertNull($cart['unavailable_toast']);

        // The shopper removes it.
        $cart = $this->onStore()->deleteJson('/cart/items/'.$cart['unavailable'][0]['key'])->assertSuccessful()->json();
        $this->assertSame([], $cart['unavailable']);
        $this->assertCount(1, $cart['items']);
    }

    public function test_a_product_shown_again_comes_back_into_the_cart(): void
    {
        $container = $this->product('Food container');
        $this->add($container);
        $container->update(['is_active' => false]);
        $this->assertCount(1, $this->onStore()->getJson('/cart/items')->json('unavailable'));

        $container->update(['is_active' => true]);

        $cart = $this->onStore()->getJson('/cart/items')->json();
        $this->assertSame([], $cart['unavailable']);
        $this->assertEquals(500, $cart['subtotal']);
    }

    public function test_a_deleted_product_and_a_switched_off_variant_are_listed(): void
    {
        $deleted = $this->product('Floor mop');
        $shirt = $this->product('Linen shirt', [
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['S', 'M']]],
            'variants' => [
                ['options' => ['Size' => 'S'], 'price' => 900, 'stock' => 2, 'is_active' => true],
                ['options' => ['Size' => 'M'], 'price' => 900, 'stock' => 2, 'is_active' => true],
            ],
        ]);
        $this->add($deleted);
        $this->add($shirt, ['Size' => 'M']);

        $deleted->delete();
        $shirt->update(['variants' => [
            ['options' => ['Size' => 'S'], 'price' => 900, 'stock' => 2, 'is_active' => true],
            ['options' => ['Size' => 'M'], 'price' => 900, 'stock' => 2, 'is_active' => false],
        ]]);

        $cart = $this->onStore()->getJson('/cart/items')->json();
        $this->assertSame([], $cart['items']);
        $this->assertSame(['Linen shirt', 'Floor mop'], array_column($cart['unavailable'], 'name'));
        $this->assertSame('Size: M', $cart['unavailable'][0]['options_label']);
        $this->assertEquals(0, $cart['subtotal']);
        $this->assertSame('Linen shirt (M) and Floor mop are no longer available. Please remove them from your cart.', $cart['unavailable_toast']);
    }

    public function test_a_sold_out_item_stays_in_the_cart_marked_out_of_stock(): void
    {
        $cleaner = $this->product('Toilet bowl cleaner');
        $spray = $this->product('Glass cleaner');
        $this->add($cleaner);
        $this->add($spray);

        $spray->update(['stock' => 0]);

        $cart = $this->onStore()->getJson('/cart/items')->json();
        $this->assertSame(['Toilet bowl cleaner'], array_column(array_column($cart['items'], 'product'), 'name'));
        $this->assertSame([['Glass cleaner', 'out_of_stock', 'Out of stock']], array_map(fn ($l) => [$l['name'], $l['reason'], $l['reason_label']], $cart['unavailable']));
        $this->assertEquals(500, $cart['subtotal']);
        $this->assertSame('Glass cleaner is out of stock. Please remove it from your cart.', $cart['unavailable_toast']);

        // Hidden as well: said again, now as no longer available.
        $spray->update(['is_active' => false]);
        $cart = $this->onStore()->getJson('/cart/items')->json();
        $this->assertSame('No longer available', $cart['unavailable'][0]['reason_label']);
        $this->assertSame('Glass cleaner is no longer available. Please remove it from your cart.', $cart['unavailable_toast']);

        // Back in stock and shown again: an ordinary line once more.
        $spray->update(['is_active' => true, 'stock' => 5]);
        $cart = $this->onStore()->getJson('/cart/items')->json();
        $this->assertSame([], $cart['unavailable']);
        $this->assertCount(2, $cart['items']);
    }

    public function test_a_sold_out_item_blocks_the_order_until_removed(): void
    {
        $spray = $this->product('Glass cleaner');
        $this->add($spray);
        $this->add($this->product('Water bottle'));
        $spray->update(['stock' => 0]);

        $this->onStore()->post('/checkout', self::CHECKOUT)->assertRedirect(route('checkout'))->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
    }

    public function test_a_variant_that_sells_out_is_marked_on_its_own(): void
    {
        $shirt = $this->product('Linen shirt', [
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['S', 'M']]],
            'variants' => [
                ['options' => ['Size' => 'S'], 'price' => 900, 'stock' => 2, 'is_active' => true],
                ['options' => ['Size' => 'M'], 'price' => 900, 'stock' => 2, 'is_active' => true],
            ],
        ]);
        $this->add($shirt, ['Size' => 'S']);
        $this->add($shirt, ['Size' => 'M']);
        $shirt->update(['variants' => [
            ['options' => ['Size' => 'S'], 'price' => 900, 'stock' => 2, 'is_active' => true],
            ['options' => ['Size' => 'M'], 'price' => 900, 'stock' => 0, 'is_active' => true],
        ]]);

        $cart = $this->onStore()->getJson('/cart/items')->json();
        $this->assertSame(['Size' => 'S'], $cart['items'][0]['options']);
        $this->assertSame(['Size: M', 'Out of stock'], [$cart['unavailable'][0]['options_label'], $cart['unavailable'][0]['reason_label']]);
    }

    public function test_no_order_is_placed_until_the_unavailable_item_is_removed(): void
    {
        $container = $this->product('Food container');
        $bottle = $this->product('Water bottle');
        $this->add($container);
        $this->add($bottle);
        $container->update(['is_active' => false]);

        $this->onStore()->post('/checkout', self::CHECKOUT)
            ->assertRedirect(route('checkout'))
            ->assertSessionHasErrors(['cart' => CartController::REMOVE_UNAVAILABLE]);
        $this->assertSame(0, Order::withoutGlobalScopes()->count());

        // The checkout page lists it, with Place order switched off while it is there.
        $this->onStore()->get('/checkout')->assertOk()
            ->assertSee('Remove it to place your order.')
            ->assertSee(':disabled="submitting || $store.cart.unavailable?.length > 0"', false);

        // Removed: the order goes through for what is left (a guest is sent for the WhatsApp code first).
        $key = $this->onStore()->getJson('/cart/items')->json('unavailable.0.key');
        $this->onStore()->deleteJson('/cart/items/'.$key)->assertSuccessful();
        $this->onStore()->post('/checkout', self::CHECKOUT)->assertSessionHasNoErrors()->assertRedirect(route('otp.verify'));
    }

    public function test_the_cart_page_lists_it_and_does_not_toast_it_again(): void
    {
        $container = $this->product('Food container');
        $this->add($container);
        $container->update(['is_active' => false]);

        // Only unavailable lines left: the page is not "empty", the checkout page sends back to it.
        $this->onStore()->get('/cart')->assertOk()->assertSee('x-for="line in ($store.cart.unavailable || [])"', false);
        $this->assertNull($this->onStore()->getJson('/cart/items')->json('unavailable_toast'));
        $this->onStore()->get('/checkout')->assertRedirect(route('cart'));
    }

    /** @return array<string, array{string}> */
    public static function templates(): array
    {
        return ['fashion' => ['fashion'], 'bulk' => ['bulk'], 'grocery' => ['grocery'], 'heritage' => ['heritage'], 'plumbing' => ['plumbing']];
    }

    #[DataProvider('templates')]
    public function test_every_template_lists_unavailable_items_in_its_drawer_cart_and_checkout(string $template): void
    {
        $store = Storefront::firstOrCreate(['slug' => $template], ['name' => ucfirst($template), 'template' => $template, 'is_active' => true]);
        $this->client->update(['storefront_id' => $store->id]);
        $container = $this->product('Food container', template: $template);
        $this->add($container);
        $this->add($this->product('Water bottle', template: $template));
        $container->update(['is_active' => false]);

        $list = 'x-for="line in ($store.cart.unavailable || [])"';
        $this->onStore()->get('/cart')->assertOk()->assertSee($list, false)->assertSee('!$store.cart.unavailable?.length', false);
        $this->onStore()->get('/checkout')->assertOk()->assertSee($list, false)->assertSee('$store.cart.unavailable?.length > 0', false);
        // The drawer on every page.
        $this->onStore()->get('/search?q=bottle')->assertOk()->assertSee($list, false);
    }
}
