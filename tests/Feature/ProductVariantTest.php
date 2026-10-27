<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product variants: flexible attributes, per-variant price, SKU and stock, and the customer's
 * choice recorded on the order.
 *
 * The storefront is the untrusted side here, so the tests push invalid combinations at the
 * add-to-bag endpoint as well as checking the happy path.
 */
class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'slug' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->client->id,
            'template' => 'fashion',
            'name' => 'Linen Shirt',
            'slug' => 'linen-shirt',
            'sku' => 'SHIRT-1',
            'price' => 2000,
            'stock' => 10,
            'is_active' => true,
        ]);
    }

    /** A product that varies by three attributes, two combinations priced differently. */
    private function variantProduct(): Product
    {
        return $this->product([
            'has_variants' => true,
            'variant_attributes' => [
                ['name' => 'Size', 'values' => ['S', 'M']],
                ['name' => 'Colour', 'values' => ['Camel', 'Ink'], 'swatches' => ['Camel' => '#B8916A']],
                ['name' => 'Material', 'values' => ['Linen', 'Silk']],
            ],
            'variants' => [
                ['options' => ['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'], 'sku' => 'SHIRT-M-CAMEL-LINEN', 'price' => 2500, 'stock' => 3],
                ['options' => ['Size' => 'S', 'Colour' => 'Ink', 'Material' => 'Silk'], 'sku' => 'SHIRT-S-INK-SILK', 'price' => 4000, 'stock' => 0],
                ['options' => ['Size' => 'S', 'Colour' => 'Camel', 'Material' => 'Linen'], 'is_active' => false],
            ],
        ]);
    }

    // ---- Attributes --------------------------------------------------------

    public function test_attributes_can_be_anything_the_client_defines(): void
    {
        $product = $this->variantProduct();

        $this->assertSame(['Size', 'Colour', 'Material'], $product->variantAttributeNames());
        $this->assertSame(['Linen', 'Silk'], $product->variantAttributes()[2]['values']);
        $this->assertSame('#B8916A', $product->variantAttributes()[1]['swatches']['Camel']);
    }

    public function test_the_older_colour_and_size_lists_are_read_as_attributes(): void
    {
        $product = $this->product([
            'colors' => [['name' => 'Camel', 'hex' => '#B8916A'], ['name' => 'Ink', 'hex' => '#151412']],
            'sizes' => ['XS', 'S', 'M'],
        ]);

        $this->assertSame(['Colour', 'Size'], $product->variantAttributeNames());
        $this->assertSame(['Camel', 'Ink'], $product->variantAttributes()[0]['values']);
        $this->assertSame(['XS', 'S', 'M'], $product->variantAttributes()[1]['values']);
        $this->assertFalse($product->sellsVariants(), 'Colour and size lists alone do not change how the product is priced.');
        $this->assertSame(2000, $product->priceFor(['Size' => 'M']));
    }

    // ---- Resolution --------------------------------------------------------

    public function test_a_combination_resolves_to_its_own_price_sku_and_stock(): void
    {
        $product = $this->variantProduct();
        $chosen = ['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'];

        $this->assertTrue($product->sellsVariants());
        $this->assertSame(2500, $product->priceFor($chosen));
        $this->assertSame(3, $product->stockFor($chosen));
        $this->assertSame('SHIRT-M-CAMEL-LINEN', $product->findVariant($chosen)['sku']);
    }

    public function test_unknown_or_incomplete_choices_resolve_to_nothing(): void
    {
        $product = $this->variantProduct();

        // A value the product does not offer is dropped.
        $this->assertSame(['Size' => 'M'], $product->normaliseOptions(['Size' => 'M', 'Colour' => 'Neon']));
        // An attribute it does not have is dropped too.
        $this->assertSame([], $product->normaliseOptions(['Flavour' => 'Mango']));
        // A partial choice matches no variant.
        $this->assertNull($product->findVariant(['Size' => 'M']));
        // A combination that exists but is switched off is not for sale.
        $this->assertNull($product->findVariant(['Size' => 'S', 'Colour' => 'Camel', 'Material' => 'Linen']));
    }

    public function test_values_are_matched_without_regard_to_case(): void
    {
        $product = $this->variantProduct();

        $this->assertSame(
            ['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'],
            $product->normaliseOptions(['size' => 'm', 'colour' => 'CAMEL', 'material' => 'linen']),
        );
    }

    // ---- Add to bag --------------------------------------------------------

    public function test_a_variant_is_added_to_the_bag_at_its_own_price(): void
    {
        $product = $this->variantProduct();

        $response = $this->withSession([\App\Models\Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
            'product_id' => $product->id,
            'options' => ['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'],
            'qty' => 2,
        ])->assertSuccessful();

        $line = $response->json('items.0');
        $this->assertSame(2500, $line['unit_price']);
        $this->assertSame(5000, $line['line_total']);
        $this->assertSame('SHIRT-M-CAMEL-LINEN', $line['variant_sku']);
        $this->assertSame('Size: M / Colour: Camel / Material: Linen', $line['options_label']);
        $this->assertSame(5000, $response->json('subtotal'));
    }

    public function test_an_incomplete_or_unavailable_choice_is_refused(): void
    {
        $product = $this->variantProduct();
        $post = fn (array $payload) => $this->withSession([\App\Models\Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', $payload + ['product_id' => $product->id]);

        // Nothing chosen
        $post([])->assertStatus(422);
        // Only part of the combination
        $post(['options' => ['Size' => 'M']])->assertStatus(422);
        // A value that does not exist
        $post(['options' => ['Size' => 'M', 'Colour' => 'Neon', 'Material' => 'Linen']])->assertStatus(422);
        // A combination with no variant
        $post(['options' => ['Size' => 'M', 'Colour' => 'Ink', 'Material' => 'Silk']])->assertStatus(422);
        // A combination that is out of stock
        $post(['options' => ['Size' => 'S', 'Colour' => 'Ink', 'Material' => 'Silk']])->assertStatus(422);
    }

    public function test_the_same_product_in_two_variants_is_two_bag_lines(): void
    {
        $product = $this->product([
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['S', 'M']]],
            'variants' => [
                ['options' => ['Size' => 'S'], 'price' => 1000, 'stock' => 5],
                ['options' => ['Size' => 'M'], 'price' => 1200, 'stock' => 5],
            ],
        ]);

        $this->withSession([\App\Models\Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', ['product_id' => $product->id, 'options' => ['Size' => 'S']])->assertSuccessful();
        $response = $this->withSession([\App\Models\Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', ['product_id' => $product->id, 'options' => ['Size' => 'M']])->assertSuccessful();

        $this->assertCount(2, $response->json('items'));
        $this->assertSame(2200, $response->json('subtotal'));
    }

    public function test_the_older_size_field_still_works(): void
    {
        $product = $this->product(['sizes' => ['S', 'M'], 'colors' => [['name' => 'Camel', 'hex' => '#B8916A']]]);

        $response = $this->withSession([\App\Models\Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
            'product_id' => $product->id,
            'size' => 'M',
            'color' => 'Camel',
        ])->assertSuccessful();

        $line = $response->json('items.0');
        $this->assertSame('M', $line['size']);
        $this->assertSame('Camel', $line['color']);
        $this->assertSame(2000, $line['unit_price']);
    }

    // ---- Orders ------------------------------------------------------------

    public function test_the_chosen_options_are_saved_with_the_order(): void
    {
        $product = $this->variantProduct();
        $customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Shopper', 'email' => 'shopper@alpha.test', 'password' => 'password123', 'is_active' => true]);

        $this->withSession([\App\Models\Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
            'product_id' => $product->id,
            'options' => ['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'],
            'qty' => 2,
        ])->assertSuccessful();

        $this->actingAs($customer, 'web')
            ->withSession([\App\Models\Client::SESSION_KEY => 'alpha'])
            ->post('/checkout', [
                'email' => 'shopper@alpha.test',
                'phone' => '9999999999',
                'shipping_name' => 'Shopper',
                'shipping_line1' => '1 Test Street',
                'shipping_city' => 'Mumbai',
                'shipping_state' => 'MH',
                'shipping_postal_code' => '400001',
                'shipping_country' => 'India',
                'shipping_method' => 'standard',
                'payment_method' => 'cod',
            ])->assertSessionHasNoErrors()->assertRedirect();

        $item = Order::withoutGlobalScopes()->latest('id')->firstOrFail()->items()->firstOrFail();

        $this->assertSame(['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'], $item->options);
        $this->assertSame('SHIRT-M-CAMEL-LINEN', $item->variant_sku);
        $this->assertSame(2500, $item->price, 'The order line is priced by the variant.');
        $this->assertSame(5000, $item->total);
        $this->assertSame('Size: M / Colour: Camel / Material: Linen', $item->optionsLabel());

        // The variant's own stock is reduced, not just the product total.
        $variant = $product->fresh()->findVariant(['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen']);
        $this->assertSame(1, $variant['stock']);
    }
}
