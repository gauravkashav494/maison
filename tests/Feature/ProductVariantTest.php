<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Support\ColourNames;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
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
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
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
                ['options' => ['Size' => 'S', 'Colour' => 'Camel', 'Material' => 'Linen'], 'stock' => 5, 'is_active' => false],
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
        $this->assertSame(2000.0, $product->priceFor(['Size' => 'M']));
    }

    // ---- Resolution --------------------------------------------------------

    public function test_a_combination_resolves_to_its_own_price_sku_and_stock(): void
    {
        $product = $this->variantProduct();
        $chosen = ['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'];

        $this->assertTrue($product->sellsVariants());
        $this->assertSame(2500.0, $product->priceFor($chosen));
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

        $response = $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
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
        $post = fn (array $payload) => $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', $payload + ['product_id' => $product->id]);

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

        $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', ['product_id' => $product->id, 'options' => ['Size' => 'S']])->assertSuccessful();
        $response = $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', ['product_id' => $product->id, 'options' => ['Size' => 'M']])->assertSuccessful();

        $this->assertCount(2, $response->json('items'));
        $this->assertSame(2200, $response->json('subtotal'));
    }

    public function test_the_older_size_field_still_works(): void
    {
        $product = $this->product(['sizes' => ['S', 'M'], 'colors' => [['name' => 'Camel', 'hex' => '#B8916A']]]);

        $response = $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
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

        $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
            'product_id' => $product->id,
            'options' => ['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'],
            'qty' => 2,
        ])->assertSuccessful();

        $this->actingAs($customer, 'web')
            ->withSession([Client::SESSION_KEY => 'alpha'])
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
        $this->assertSame(2500.0, $item->price, 'The order line is priced by the variant.');
        $this->assertSame(5000.0, $item->total);
        $this->assertSame('Size: M / Colour: Camel / Material: Linen', $item->optionsLabel());

        // The variant's own stock is reduced, not just the product total.
        $variant = $product->fresh()->findVariant(['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen']);
        $this->assertSame(1, $variant['stock']);
    }

    // ---- Admin form --------------------------------------------------------

    private function actAsClientAdmin(): void
    {
        $admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin);
    }

    public function test_a_combination_picks_each_attribute_from_a_dropdown_of_its_values(): void
    {
        $product = $this->variantProduct();
        $this->actAsClientAdmin();

        $html = $this->get('/client/products/'.$product->id.'/edit')->assertSuccessful()->getContent();

        $this->assertStringNotContainsString('Chosen values', $html, 'The free-text key/value box is gone.');
        foreach (['options.Size', 'options.Colour', 'options.Material'] as $path) {
            $this->assertStringContainsString($path, $html, "A dropdown is bound to {$path}.");
        }
        foreach (['Camel', 'Ink', 'Linen', 'Silk'] as $value) {
            $this->assertStringContainsString($value, $html);
        }
    }

    public function test_an_image_whose_file_is_gone_is_dropped_when_the_form_opens(): void
    {
        // A missing file would leave the upload box on "Loading" for ever; the field drops it.
        Storage::fake('public');
        Storage::disk('public')->put('products/kept.jpg', 'x');
        $product = $this->product(['images' => ['products/kept.jpg', 'products/gone.jpg', 'https://example.test/sample.jpg']]);
        $this->actAsClientAdmin();

        $images = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->get('data.images');

        $this->assertSame(['products/kept.jpg', 'https://example.test/sample.jpg'], array_values($images));
    }

    public function test_saved_images_are_handed_to_the_upload_box_on_the_same_host(): void
    {
        // The disk URL carries APP_URL; on a client's subdomain the box's fetch of that URL is
        // blocked as cross-origin, so it gets a path on whatever host the panel is on.
        Storage::fake('public');
        Storage::disk('public')->put('products/kept.jpg', 'x');
        $product = $this->product(['images' => ['products/kept.jpg']]);
        $this->actAsClientAdmin();

        $field = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->instance()->form
            ->getComponent(fn ($component) => $component instanceof FileUpload && $component->getName() === 'images', withHidden: true);
        $files = $field->getUploadedFiles();

        $this->assertSame('/storage/products/kept.jpg', reset($files)['url']);
    }

    public function test_saving_the_form_keeps_each_combinations_chosen_values(): void
    {
        $product = $this->variantProduct();
        // The fields the form requires besides the variants.
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'images' => ['products/shirt.jpg']]);
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $variants = $page->get('data.variants');
        $first = array_key_first($variants);
        $this->assertSame(['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'], $variants[$first]['options']);

        $page->set("data.variants.{$first}.options.Size", 'S')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = Product::withoutGlobalScopes()->findOrFail($product->id);
        $this->assertSame(['Size' => 'S', 'Colour' => 'Camel', 'Material' => 'Linen'], $saved->variants[0]['options']);
        $this->assertSame(['Size' => 'S', 'Colour' => 'Ink', 'Material' => 'Silk'], $saved->variants[1]['options']);
    }

    public function test_a_colour_attribute_gets_a_colour_picker_for_each_value(): void
    {
        $product = $this->variantProduct();
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'images' => ['products/shirt.jpg']]);
        $this->actAsClientAdmin();

        $html = $this->get('/client/products/'.$product->id.'/edit')->assertSuccessful()->getContent();
        $this->assertStringContainsString('swatches.Camel', $html);
        $this->assertStringContainsString('swatches.Ink', $html);
        $this->assertStringNotContainsString('swatches.Linen', $html, 'Only the colour attribute has swatches.');

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $attributes = $page->get('data.variant_attributes');
        $colour = collect($attributes)->search(fn ($a) => $a['name'] === 'Colour');
        $this->assertSame('#B8916A', $attributes[$colour]['swatches']['Camel']);

        $page->set("data.variant_attributes.{$colour}.swatches.Ink", '#1F2A44')
            ->call('save')
            ->assertHasNoFormErrors();

        $swatches = collect(Product::withoutGlobalScopes()->findOrFail($product->id)->variantAttributes())->firstWhere('name', 'Colour')['swatches'];
        $this->assertSame(['Camel' => '#B8916A', 'Ink' => '#1F2A44'], $swatches);
    }

    // ---- Options kept in step with what the storefront renders ------------

    /** A product the admin form can save (it requires a category and an image). */
    private function editableVariantProduct(): Product
    {
        $product = $this->variantProduct();
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'images' => ['products/shirt.jpg']]);

        return $product;
    }

    public function test_size_and_colour_options_fill_the_lists_the_templates_render(): void
    {
        $product = $this->variantProduct()->fresh();

        $this->assertSame(['S', 'M'], $product->sizes);
        $this->assertSame([['name' => 'Camel', 'hex' => '#B8916A'], ['name' => 'Ink', 'hex' => null]], $product->colors);

        // A list with no matching option is left as it is.
        $product->update(['variant_attributes' => [['name' => 'Material', 'values' => ['Linen']]]]);
        $this->assertSame(['S', 'M'], $product->fresh()->sizes);
    }

    public function test_a_display_colour_beside_a_size_option_is_kept_and_stays_editable(): void
    {
        // Like the seeded kada bangle: sold by size, shown in one colour that is not a choice.
        $product = $this->product([
            'name' => 'Kada', 'slug' => 'kada', 'sku' => 'KADA-1',
            'colors' => [['name' => 'Rose gold', 'hex' => '#D7A07C']],
            'sizes' => ['2.4', '2.6'],
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['2.4', '2.6']]],
            'variants' => [['options' => ['Size' => '2.4'], 'price' => 4200, 'stock' => 12]],
        ]);
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Bangles', 'slug' => 'bangles', 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'images' => ['products/kada.jpg']]);
        $this->assertSame([['name' => 'Rose gold', 'hex' => '#D7A07C']], $product->fresh()->colors);

        $this->actAsClientAdmin();
        $this->get('/client/products/'.$product->id.'/edit')->assertSuccessful()->assertSee('Older colour &amp; size lists', false);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $saved = Product::withoutGlobalScopes()->findOrFail($product->id);
        $this->assertSame([['name' => 'Rose gold', 'hex' => '#D7A07C']], $saved->colors);
        $this->assertSame(['2.4', '2.6'], $saved->sizes);

        // A product whose lists are all options does not show the older section.
        $other = $this->editableVariantProduct();
        $this->get('/client/products/'.$other->id.'/edit')->assertSuccessful()->assertDontSee('Older colour &amp; size lists', false);
    }

    public function test_the_storefront_shows_size_and_colour_options_defined_as_options(): void
    {
        $product = $this->editableVariantProduct();
        app(ClientContext::class)->resolveForPublic($this->client);

        $this->get($this->client->publicUrl().'/product/'.$product->slug)
            ->assertSuccessful()
            ->assertSee("@click=\"size = 'S'", false)        // the template's own size picker
            ->assertSee('aria-label="Camel"', false)          // a swatch: Camel has a colour
            ->assertSee('>Ink</button>', false)               // no colour set: shown as its name
            ->assertSee('>Linen</button>', false);            // other options: the shared picker
    }

    public function test_a_combination_loses_values_for_options_that_no_longer_exist(): void
    {
        $product = $this->variantProduct();
        $product->update(['variant_attributes' => [
            ['name' => 'Size', 'values' => ['S', 'M']],
            ['name' => 'Colour', 'values' => ['Camel', 'Ink']],
        ]]);

        $this->assertSame(['Size' => 'M', 'Colour' => 'Camel'], $product->fresh()->variants[0]['options']);
        $this->assertNotNull($product->fresh()->findVariant(['Size' => 'M', 'Colour' => 'Camel']));
    }

    public function test_colour_and_color_are_the_same_option(): void
    {
        $product = $this->product([
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Color', 'values' => ['Red', 'Blue']]],
            'variants' => [['options' => ['Color' => 'Red'], 'price' => 2100, 'stock' => 4]],
        ]);

        // The storefront posts the template's colour picker as "Colour".
        $this->assertSame(['Color' => 'Red'], $product->normaliseOptions(['Colour' => 'red']));
        $this->assertSame(2100.0, $product->priceFor(['Colour' => 'Red']));
    }

    public function test_a_combinations_photos_are_sent_to_the_gallery(): void
    {
        $product = $this->product([
            'images' => ['products/shirt.jpg'],
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Colour', 'values' => ['Camel', 'Ink']]],
            'variants' => [
                ['options' => ['Colour' => 'Camel'], 'stock' => 2, 'images' => ['products/camel.jpg']],
                ['options' => ['Colour' => 'Ink'], 'stock' => 2],
            ],
        ]);

        $card = $product->toCard();
        $this->assertCount(1, $card['variant_images']);
        $this->assertSame(['Colour' => 'Camel'], $card['variant_images'][0]['options']);
        $this->assertStringEndsWith('products/camel.jpg', $card['variant_images'][0]['images'][0]);
    }

    public function test_generate_all_combinations_adds_the_missing_ones_and_keeps_the_rest(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(TestAction::make('generateCombinations')->schemaComponent('combinations', schema: 'form'));

        $variants = array_values($page->get('data.variants'));
        $this->assertCount(8, $variants, '2 sizes × 2 colours × 2 materials.');
        // Listed in option order: every S first, then every M.
        $this->assertSame(
            ['S Camel Linen', 'S Camel Silk', 'S Ink Linen', 'S Ink Silk', 'M Camel Linen', 'M Camel Silk', 'M Ink Linen', 'M Ink Silk'],
            array_map(fn ($v) => implode(' ', $v['options']), $variants),
        );
        $this->assertSame('SHIRT-M-CAMEL-LINEN', $variants[4]['sku'], 'Existing combinations keep their details.');
        $this->assertSame(3, (int) $variants[4]['stock']);
        $this->assertSame(0, $variants[1]['stock'], 'New combinations start with no stock.');

        // Running it again adds nothing.
        $page->callAction(TestAction::make('generateCombinations')->schemaComponent('combinations', schema: 'form'));
        $this->assertCount(8, $page->get('data.variants'));
    }

    public function test_a_combination_must_have_its_own_stock(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $first = array_key_first($page->get('data.variants'));

        $page->set("data.variants.{$first}.stock", null)
            ->call('save')
            ->assertHasFormErrors(["variants.{$first}.stock" => 'required']);
    }

    public function test_renaming_an_option_carries_the_combinations_values_over(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $material = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Material');

        $page->set("data.variant_attributes.{$material}.name", 'Fabric')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = Product::withoutGlobalScopes()->findOrFail($product->id);
        $this->assertSame(['Size' => 'M', 'Colour' => 'Camel', 'Fabric' => 'Linen'], $saved->variants[0]['options']);
    }

    public function test_an_older_product_opens_with_its_sizes_and_colours_as_options(): void
    {
        $product = $this->product([
            'colors' => [['name' => 'Sand', 'hex' => '#D8C8A8']],
            'sizes' => ['S', 'M', 'L'],
        ]);
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'images' => ['products/shirt.jpg']]);
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $options = array_values($page->get('data.variant_attributes'));
        $this->assertSame(['Colour', 'Size'], array_column($options, 'name'));
        $this->assertSame(['Sand' => '#D8C8A8'], $options[0]['swatches']);

        $page->call('save')->assertHasNoFormErrors();
        $saved = Product::withoutGlobalScopes()->findOrFail($product->id);
        $this->assertSame(['S', 'M', 'L'], $saved->sizes, 'Saving keeps the lists the storefront reads.');
        $this->assertSame([['name' => 'Sand', 'hex' => '#D8C8A8']], $saved->colors);
    }

    // ---- Swatches from colour names ----------------------------------------

    public function test_colour_values_named_like_colours_get_swatches_unless_one_was_picked(): void
    {
        $this->assertSame('#1f2a44', ColourNames::hex('Navy Blue'));
        $this->assertSame('#f5f1e8', ColourNames::hex('off-white'));
        $this->assertNull(ColourNames::hex('Ink'));

        $product = $this->product([
            'variant_attributes' => [['name' => 'color', 'values' => ['red', 'Yellow', 'Ink'], 'swatches' => ['red' => '#aa0000']]],
        ]);

        $swatches = $product->variantAttributes()[0]['swatches'];
        $this->assertSame('#aa0000', $swatches['red'], 'A picked colour wins.');
        $this->assertSame('#fbc02d', $swatches['Yellow']);
        $this->assertArrayNotHasKey('Ink', $swatches, 'An unknown name stays text.');
        $this->assertSame([['name' => 'red', 'hex' => '#aa0000'], ['name' => 'Yellow', 'hex' => '#fbc02d'], ['name' => 'Ink', 'hex' => null]], $product->fresh()->colors);
    }

    public function test_typing_a_colour_value_in_the_form_fills_its_swatch(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $colour = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Colour');

        $page->set("data.variant_attributes.{$colour}.values", ['Camel', 'Ink', 'Maroon']);

        $this->assertSame('#800000', $page->get("data.variant_attributes.{$colour}.swatches.Maroon"));
        $this->assertSame('#B8916A', $page->get("data.variant_attributes.{$colour}.swatches.Camel"), 'An existing pick is kept.');
    }

    // ---- Option dropdown -----------------------------------------------------

    public function test_options_open_with_their_preset_or_other(): void
    {
        $product = $this->editableVariantProduct();
        $product->update(['variant_attributes' => [
            ['name' => 'Size', 'values' => ['S', 'M']],
            ['name' => 'color', 'values' => ['Camel', 'Ink']],
            ['name' => 'Fragrance', 'values' => ['Rose']],
        ]]);
        $this->actAsClientAdmin();

        $rows = array_values(Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->get('data.variant_attributes'));
        $this->assertSame(['Size', 'Colour', '__other'], array_column($rows, 'preset'));
        $this->assertSame(['Size', 'color', 'Fragrance'], array_column($rows, 'name'), 'The saved names are untouched.');
    }

    public function test_choosing_a_preset_or_other_sets_the_option_name(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $material = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Material');

        // Other… keeps "Material" in the box; renaming it carries the combinations over.
        $page->set("data.variant_attributes.{$material}.preset", '__other')
            ->assertSet("data.variant_attributes.{$material}.name", 'Material')
            ->set("data.variant_attributes.{$material}.name", 'Fabric')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = Product::withoutGlobalScopes()->findOrFail($product->id);
        $this->assertSame('Fabric', $saved->variant_attributes[2]['name']);
        $this->assertSame(['Size' => 'M', 'Colour' => 'Camel', 'Fabric' => 'Linen'], $saved->variants[0]['options']);
        $this->assertArrayNotHasKey('preset', $saved->variant_attributes[2], 'Only the name is stored.');

        // Back to a preset: the name becomes the preset's.
        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $fabric = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Fabric');
        $page->set("data.variant_attributes.{$fabric}.preset", 'Style')
            ->assertSet("data.variant_attributes.{$fabric}.name", 'Style')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(['Size' => 'M', 'Colour' => 'Camel', 'Style' => 'Linen'], Product::withoutGlobalScopes()->findOrFail($product->id)->variants[0]['options']);
    }

    public function test_an_unpicked_colour_value_opens_with_its_named_colour(): void
    {
        $product = $this->editableVariantProduct();
        $product->update(['variant_attributes' => [['name' => 'Colour', 'values' => ['Red', 'Ink']]]]);
        $this->actAsClientAdmin();

        $row = array_values(Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->get('data.variant_attributes'))[0];
        $this->assertSame('#d32f2f', $row['swatches']['Red'] ?? null);
        $this->assertEmpty($row['swatches']['Ink'] ?? null, 'An unknown name stays text only.');
    }

    public function test_choosing_an_option_fills_its_common_values_on_an_empty_row(): void
    {
        $product = $this->editableVariantProduct();
        $product->update(['variant_attributes' => [
            ['name' => 'Size', 'values' => ['S', 'M']],
            ['name' => 'Weight', 'values' => []],
        ]]);
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $keys = array_keys($page->get('data.variant_attributes'));

        // An empty row: the option's common values are filled in (here Colour, with swatches).
        $page->set("data.variant_attributes.{$keys[1]}.preset", 'Colour')
            ->assertSet("data.variant_attributes.{$keys[1]}.values", ['Black', 'White', 'Red', 'Blue'])
            ->assertSet("data.variant_attributes.{$keys[1]}.swatches.Red", '#d32f2f');

        // A row with values keeps them when the option changes.
        $page->set("data.variant_attributes.{$keys[0]}.preset", 'Pack size')
            ->assertSet("data.variant_attributes.{$keys[0]}.values", ['S', 'M']);

        // Trimmed and extended by hand, then saved as entered.
        $page->set("data.variant_attributes.{$keys[1]}.values", ['Black', 'Red', 'Teal blue'])
            ->set('data.has_variants', false)
            ->call('save')
            ->assertHasNoFormErrors();
        $saved = Product::withoutGlobalScopes()->findOrFail($product->id)->variant_attributes;
        $this->assertSame(['Black', 'Red', 'Teal blue'], $saved[1]['values']);
    }

    public function test_the_values_box_suggests_the_options_common_values(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $this->get('/client/products/'.$product->id.'/edit')->assertSuccessful()
            ->assertSee('XXL')            // Size suggestions
            ->assertSee('Multicolour')    // Colour suggestions
            ->assertSee('Add a value — pick a suggestion or type your own', false);
    }

    public function test_an_option_already_added_cannot_be_chosen_again_in_another_row(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $material = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Material');

        // Each other row's option shows as unavailable in this row's dropdown; "Other…" stays open.
        $html = $page->html();
        // Three rows: each option is open in its own row and unavailable in the other two.
        $this->assertSame(2, preg_match_all('/<option\s+disabled\s+value="Size"/', $html));
        $this->assertSame(2, preg_match_all('/<option\s+disabled\s+value="Colour"/', $html));
        $this->assertSame(0, preg_match_all('/<option\s+disabled\s+value="__other"/', $html));

        // Choosing it anyway is refused on save.
        $page->set("data.variant_attributes.{$material}.preset", 'Size')
            ->call('save')
            ->assertHasFormErrors(["variant_attributes.{$material}.preset"]);
    }

    public function test_a_custom_option_name_already_used_is_refused(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $material = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Material');

        // "Color" is the Colour option under another spelling.
        $page->set("data.variant_attributes.{$material}.name", 'Color')
            ->call('save')
            ->assertHasFormErrors(["variant_attributes.{$material}.name"]);

        $this->assertSame(['Size', 'Colour', 'Material'], array_column(Product::withoutGlobalScopes()->findOrFail($product->id)->variant_attributes, 'name'));
    }

    public function test_rows_listed_before_an_option_was_added_are_split_into_its_combinations(): void
    {
        $product = $this->product([
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['250 ml', '750 ml']]],
            'variants' => [
                ['options' => ['Size' => '250 ml'], 'sku' => 'PK-CT-250', 'price' => 210, 'stock' => 40, 'is_active' => true],
                ['options' => ['Size' => '750 ml'], 'sku' => 'PK-CT-750', 'price' => 310, 'stock' => 220, 'is_active' => false],
            ],
        ]);
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Paint', 'slug' => 'paint', 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'images' => ['products/paint.jpg']]);
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $page->set('data.variant_attributes', $page->get('data.variant_attributes') + [
            (string) Str::uuid() => ['name' => 'Finish', 'preset' => 'Finish', 'values' => ['Matte', 'Glossy'], 'swatches' => []],
        ]);

        $page->callAction(TestAction::make('generateCombinations')->schemaComponent('combinations', schema: 'form'))
            ->assertNotified();

        $variants = array_values($page->get('data.variants'));
        $this->assertSame(
            ['250 ml Matte', '250 ml Glossy', '750 ml Matte', '750 ml Glossy'],
            array_map(fn ($v) => implode(' ', $v['options']), $variants),
            'The size-only rows are replaced by their combinations, none left half-filled.',
        );
        $this->assertSame(['PK-CT-250-MATTE', 'PK-CT-250-GLOSSY', 'PK-CT-750-MATTE', 'PK-CT-750-GLOSSY'], array_column($variants, 'sku'));
        $this->assertEquals([210, 210, 310, 310], array_column($variants, 'price'), 'Each keeps the price of the row it came from.');
        $this->assertSame([true, true, false, false], array_column($variants, 'is_active'));
        $this->assertSame([0, 0, 0, 0], array_column($variants, 'stock'), 'Stock cannot be split, so it is set per row.');
    }

    public function test_an_earlier_row_fills_the_blank_combinations_a_previous_generate_added(): void
    {
        $product = $this->product([
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['750 ml']], ['name' => 'Finish', 'values' => ['Matte', 'Glossy']]],
            'variants' => [
                ['options' => ['Size' => '750 ml'], 'sku' => 'PK-CT-750', 'price' => 310, 'stock' => 220, 'is_active' => true],
                // Added by an earlier generate, left blank, and one already given its own price.
                ['options' => ['Size' => '750 ml', 'Finish' => 'Matte'], 'sku' => null, 'price' => null, 'stock' => 4, 'is_active' => true],
                ['options' => ['Size' => '750 ml', 'Finish' => 'Glossy'], 'sku' => null, 'price' => 330, 'stock' => 0, 'is_active' => true],
            ],
        ]);
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Paint', 'slug' => 'paint', 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'images' => ['products/paint.jpg']]);
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(TestAction::make('generateCombinations')->schemaComponent('combinations', schema: 'form'));

        $variants = array_values($page->get('data.variants'));
        $this->assertSame(['750 ml Matte', '750 ml Glossy'], array_map(fn ($v) => implode(' ', $v['options']), $variants), 'The size-only row is gone.');
        $this->assertSame(['PK-CT-750-MATTE', 'PK-CT-750-GLOSSY'], array_column($variants, 'sku'));
        $this->assertEquals([310, 330], array_column($variants, 'price'), 'Only blanks are filled; a price already set stays.');
        $this->assertEquals([4, 0], array_column($variants, 'stock'));
    }
}
