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
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_a_combinations_existing_photos_are_kept_and_stock_is_their_total(): void
    {
        $product = $this->variantProductWithPhotos();
        // Saved before the photo column was removed from the combinations table.
        $variants = $product->variants;
        $variants[0]['images'] = ['products/back.jpg'];
        $product->update(['variants' => $variants]);
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $html = $page->html();
        $table = substr($html, strpos($html, 'Generate all variants'));
        preg_match('#<thead.*?</thead>#s', $table, $head);
        preg_match('#<tbody.*?</tbody>#s', $table, $body);
        preg_match('#<tr.*?</tr>#s', $body[0] ?? '', $row);
        // No photo column, and the hidden field adds no empty cell: one cell per heading.
        $this->assertStringNotContainsString('>Photos<', $head[0]);
        $this->assertSame(preg_match_all('#<th[\s>]#', $head[0]), preg_match_all('#<td[\s>]#', $row[0]));
        $page
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $product->fresh();
        $this->assertSame(['products/back.jpg'], $saved->variants[0]['images']);
        // Product stock is not asked a second time: it is saved as the available combinations' total
        // (3 + 0; the third is unavailable).
        $this->assertSame(3, (int) $saved->stock);
        // Combinations saved without an availability flag stayed available.
        $this->assertSame([true, true, false], array_map(fn ($v) => (bool) $v['is_active'], $saved->variants));
    }

    /** A variant product with two photos uploaded in Media and a category, ready to save through the form. */
    private function variantProductWithPhotos(): Product
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/front.jpg', 'x');
        Storage::disk('public')->put('products/back.jpg', 'x');
        $product = $this->variantProduct();
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);
        $product->update(['category_id' => $category->id, 'images' => ['products/front.jpg', 'products/back.jpg'], 'stock' => 999]);

        return $product;
    }

    public function test_photos_are_chosen_per_colour_and_a_combinations_own_photos_override_them(): void
    {
        $product = $this->variantProductWithPhotos();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $rows = $page->get('data.variant_attributes');
        $colour = collect($rows)->search(fn ($row) => $row['name'] === 'Colour');

        // No switch: the colour option has photo pickers, and nothing is picked until the store picks it.
        $this->assertSame([], array_filter((array) ($rows[$colour]['images'] ?? [])));

        $page->set("data.variant_attributes.{$colour}.images.Camel", ['products/back.jpg'])
            ->set("data.variant_attributes.{$colour}.images.Ink", ['products/front.jpg'])
            ->call('save')
            ->assertHasNoFormErrors();

        $sets = collect($product->fresh()->toCard()['variant_images']);
        // Every size of Camel shows the camel photo, from one choice, not per combination.
        $this->assertSame(['Colour' => 'Camel'], $sets->first()['options']);
        $this->assertStringEndsWith('products/back.jpg', $sets->first()['images'][0]);
        $this->assertStringEndsWith('products/front.jpg', $sets->firstWhere('options', ['Colour' => 'Ink'])['images'][0]);
    }

    public function test_only_the_colour_option_changes_the_photos(): void
    {
        $product = $this->variantProductWithPhotos();
        $attributes = $product->variant_attributes;
        foreach ($attributes as &$attribute) {
            // Photos saved on a non-colour option are ignored; only Colour switches the gallery.
            $attribute['images'] = [$attribute['values'][0] => ['products/back.jpg']];
        }
        $product->update(['variant_attributes' => $attributes]);

        $this->assertSame([['Colour' => 'Camel']], array_column($product->fresh()->optionImages(), 'options'));
    }

    public function test_a_photo_removed_from_media_is_no_longer_shown_for_its_colour(): void
    {
        $product = $this->variantProductWithPhotos();
        $attributes = $product->variant_attributes;
        foreach ($attributes as &$attribute) {
            if ($attribute['name'] === 'Colour') {
                $attribute['images'] = ['Camel' => ['products/back.jpg', 'products/deleted.jpg']];
            }
        }
        $product->update(['variant_attributes' => $attributes]);

        $sets = $product->fresh()->optionImages();
        $this->assertCount(1, $sets[0]['images']);
        $this->assertStringEndsWith('products/back.jpg', $sets[0]['images'][0]);
    }

    public function test_a_card_shows_a_colours_photo_while_its_swatch_is_hovered(): void
    {
        $product = $this->variantProductWithPhotos();
        $attributes = $product->variant_attributes;
        foreach ($attributes as &$attribute) {
            if ($attribute['name'] === 'Colour') {
                $attribute['images'] = ['Ink' => ['products/front.jpg']];
            }
        }
        $product->update(['variant_attributes' => $attributes]);
        $product = $product->fresh();

        // Ink has a photo to show, Camel has none (on a card its swatch changes nothing).
        $colours = collect($product->cardColours())->keyBy('name');
        $this->assertStringEndsWith('products/front.jpg', $colours['Ink']['image']);
        $this->assertNull($colours['Camel']['image']);

        $html = (string) $this->blade('<x-card-colours :product="$p" /><x-card-colour-photo :product="$p" />', ['p' => $product]);
        $this->assertStringContainsString('card-colour has-photo', $html);
        $this->assertStringContainsString("card-colour', { id: {$product->id}", $html);
        $this->assertStringContainsString('card-colour-photo', $html);

        // On a product page (Bulk) a click keeps the colour shown instead of opening the product.
        $page = (string) $this->blade('<x-card-colours :product="$p" :select="true" />', ['p' => $product]);
        $this->assertStringContainsString('<button type="button" title="Ink"', $page);
        $this->assertStringContainsString('x-bind:aria-pressed', $page);
        // A colour without photos can be chosen too: it shows the product's own picture.
        $this->assertDoesNotMatchRegularExpression('/title="Camel"[^>]*disabled/s', $page);
        $this->assertStringContainsString("chosen === 'Camel'", $page);

        // A product whose colours have no photos keeps its card as it was.
        $plain = $this->product(['slug' => 'plain-shirt', 'sku' => 'PLAIN-1', 'variant_attributes' => [['name' => 'Colour', 'values' => ['Red', 'Blue']]]]);
        $this->assertSame('', trim((string) $this->blade('<x-card-colours :product="$p" /><x-card-colour-photo :product="$p" />', ['p' => $plain])));
        // …except the Fashion card, which always showed its colours: still shown, just not hoverable.
        $dots = (string) $this->blade('<x-card-colours :product="$p" :with-photos-only="false" />', ['p' => $plain]);
        $this->assertStringContainsString('title="Red"', $dots);
        $this->assertStringNotContainsString('card-colour has-photo', $dots);
    }

    public function test_photos_uploaded_under_a_colour_join_the_images_and_are_picked_for_it(): void
    {
        $product = $this->variantProductWithPhotos();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $colour = collect($page->get('data.variant_attributes'))->search(fn ($row) => $row['name'] === 'Colour');

        $page->set("data.variant_attributes.{$colour}.uploads.Ink", [UploadedFile::fake()->image('Ink Front.JPG', 20, 20)])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $product->fresh();
        $new = collect($saved->images)->first(fn ($p) => str_contains($p, 'ink-front-'));
        $this->assertNotNull($new, 'The upload joins the product images, named after the original file.');
        $this->assertMatchesRegularExpression('#/ink-front-[0-9a-z]{26}\.jpg$#', $new);
        Storage::disk('public')->assertExists($new);
        $ink = collect($saved->variant_attributes)->firstWhere('name', 'Colour')['images']['Ink'];
        $this->assertSame([$new], $ink);
        $this->assertArrayNotHasKey('uploads', collect($saved->variant_attributes)->firstWhere('name', 'Colour'));

        // The form now shows the product as saved, so saving again keeps it.
        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame([$new], collect($product->fresh()->variant_attributes)->firstWhere('name', 'Colour')['images']['Ink']);
    }

    public function test_new_photos_named_after_a_colour_are_picked_for_it(): void
    {
        // A new product's photos are sorted too; then the store unticks the old one for Ink.
        $product = $this->product([
            'images' => ['products/old-ink-photo.jpg'],
            'variant_attributes' => [['name' => 'Colour', 'values' => ['Blue', 'Navy blue', 'Ink', 'Camel']]],
        ]);
        $this->assertSame(['products/old-ink-photo.jpg'], $product->variant_attributes[0]['images']['Ink']);
        $product->update(['variant_attributes' => [['name' => 'Colour', 'values' => ['Blue', 'Navy blue', 'Ink', 'Camel'], 'images' => ['Ink' => []]]]]);

        $product->update(['images' => [
            'products/old-ink-photo.jpg',       // already there and unticked: left alone
            'products/shirt-ink-1-01jx.jpg',    // Ink
            'products/shirt-navy-blue-01jx.jpg', // Navy blue, not Blue
            'products/shirt-blue-01jx.jpg',     // Blue
            'products/ink-and-camel-01jx.jpg',  // two colours: unclear, left for the store
            'products/blueberry-01jx.jpg',      // a word, not the colour
        ]]);

        $picked = collect($product->fresh()->variant_attributes)->firstWhere('name', 'Colour')['images'] ?? [];
        $this->assertSame(['products/shirt-ink-1-01jx.jpg'], $picked['Ink']);
        $this->assertSame(['products/shirt-navy-blue-01jx.jpg'], $picked['Navy blue']);
        $this->assertSame(['products/shirt-blue-01jx.jpg'], $picked['Blue']);
        $this->assertArrayNotHasKey('Camel', $picked);
    }

    public function test_options_and_combinations_share_one_variants_tab(): void
    {
        $product = $this->variantProduct();
        $this->actAsClientAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('Variants')
            ->assertSee('Step 1 · Options')
            ->assertSee('Step 2 · Variants (price &amp; stock for each)', false)
            ->assertSee('Default price')
            ->assertSee('Camel photos')
            ->assertDontSee('Photos change with this option')
            // Merchandising moved to the Details tab with price and stock merged in here.
            ->assertSee('Merchandising')
            ->assertSee('Visible on storefront')
            ->assertSee('Best seller');
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

    public function test_renaming_a_value_keeps_its_swatch_photos_and_combinations(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $colour = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Colour');
        $page->set("data.variant_attributes.{$colour}.images.Camel", ['products/shirt.jpg']);

        // A chip edited in place: same position, new text.
        $page->set("data.variant_attributes.{$colour}.values", ['Sand', 'Ink'])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = Product::withoutGlobalScopes()->findOrFail($product->id);
        $attribute = collect($saved->variant_attributes)->firstWhere('name', 'Colour');
        $this->assertSame(['Sand', 'Ink'], $attribute['values']);
        $this->assertSame('#B8916A', $attribute['swatches']['Sand']);
        $this->assertArrayNotHasKey('Camel', $attribute['swatches']);
        $this->assertSame(['products/shirt.jpg'], $attribute['images']['Sand']);
        $this->assertSame(['Sand', 'Ink', 'Sand'], array_map(fn ($v) => $v['options']['Colour'], $saved->variants));
        $this->assertSame('SHIRT-M-CAMEL-LINEN', $saved->variants[0]['sku'], 'The combination itself is kept, not recreated.');
    }

    public function test_removing_and_adding_a_value_is_not_a_rename(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $size = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Size');

        // Adding a value leaves the combinations alone.
        $page->set("data.variant_attributes.{$size}.values", ['S', 'M', 'L']);
        $this->assertSame(['M', 'S', 'S'], array_values(array_map(fn ($v) => $v['options']['Size'], $page->get('data.variants'))));

        // Two values changed at once is not one rename: both count as removed, with their combinations.
        $page->set("data.variant_attributes.{$size}.values", ['XS', 'XL', 'L']);
        $this->assertSame([], $page->get('data.variants'));
    }

    public function test_removing_a_value_removes_the_variants_that_chose_it(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $colour = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Colour');

        $page->set("data.variant_attributes.{$colour}.values", ['Camel'])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = Product::withoutGlobalScopes()->findOrFail($product->id);
        $this->assertSame(['Camel', 'Camel'], array_map(fn ($v) => $v['options']['Colour'], $saved->variants));
        $this->assertSame('SHIRT-M-CAMEL-LINEN', $saved->variants[0]['sku']);
    }

    public function test_the_values_box_carries_how_many_variants_use_each_value(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        // The × warning reads these counts; values no variant uses are not listed.
        $html = $this->get('/client/products/'.$product->id.'/edit')->assertSuccessful()->getContent();
        $this->assertStringContainsString('data-value-uses="'.rawurlencode(json_encode(['Camel' => 2, 'Ink' => 1])).'"', $html);
        $this->assertStringContainsString('data-value-uses="'.rawurlencode(json_encode(['Linen' => 2, 'Silk' => 1])).'"', $html);
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
            ->assertSee('Type a value, press Enter', false);
    }

    public function test_an_option_already_added_cannot_be_chosen_again_in_another_row(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);
        $material = collect($page->get('data.variant_attributes'))->search(fn ($a) => $a['name'] === 'Material');

        // Each other row's option shows as unavailable in this row's dropdown; "Other…" stays open.
        // Three rows: each option is open in its own row and unavailable in the other two.
        $presets = array_map(
            fn (string $row) => $page->instance()->form->getComponent(
                fn ($component) => $component instanceof Select && $component->getStatePath() === "data.variant_attributes.{$row}.preset",
                withHidden: true,
            ),
            array_keys($page->get('data.variant_attributes')),
        );
        $disabled = fn (string $value) => count(array_filter($presets, fn (Select $preset) => $preset->isOptionDisabled($value, $value)));
        $this->assertCount(3, array_filter($presets));
        $this->assertSame(2, $disabled('Size'));
        $this->assertSame(2, $disabled('Colour'));
        $this->assertSame(0, $disabled('__other'));

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

    // ---- Compare-at price per variant ---------------------------------------

    /** The variant product with a product compare-at of ₹3,000 and one variant priced "was ₹5,000". */
    private function discountedVariantProduct(): Product
    {
        $product = $this->variantProduct();
        $variants = $product->variants;
        $variants[1]['compare_at_price'] = 5000; // S / Ink / Silk at ₹4,000
        $variants[1]['stock'] = 2;
        $product->update(['compare_at_price' => 3000, 'variants' => $variants]);

        return $product->fresh();
    }

    public function test_a_variant_has_its_own_compare_at_price_or_the_products(): void
    {
        $product = $this->discountedVariantProduct();
        $list = collect($product->variantList())->keyBy('label');

        $this->assertSame(5000.0, $list['S / Ink / Silk']['compare_at_price'], 'Its own compare-at price.');
        $this->assertSame(3000.0, $list['M / Camel / Linen']['compare_at_price'], 'A blank uses the product\'s, when above the variant\'s ₹2,500.');

        // The product's ₹3,000 is not above a ₹4,000 variant: no discount, rather than a negative one.
        $variants = $product->variants;
        unset($variants[1]['compare_at_price']);
        $product->update(['variants' => $variants]);
        $this->assertNull(collect($product->fresh()->variantList())->keyBy('label')['S / Ink / Silk']['compare_at_price']);
    }

    public function test_the_storefront_pricing_carries_each_variants_saving(): void
    {
        $product = $this->discountedVariantProduct();
        $silk = collect($product->pricingVariants())->firstWhere('label', 'S / Ink / Silk');

        $this->assertSame(money(5000), $silk['compare_at_price_formatted']);
        $this->assertSame(20, $silk['discount_percent']);
        $this->assertSame(money(1000), $silk['save_formatted']);
        // Before a choice: the cheapest variant in stock (M / Camel / Linen at ₹2,500, was the product's ₹3,000).
        $this->assertSame(['price' => 2500.0, 'compare_at_price' => 3000.0, 'discount_percent' => 17], array_intersect_key($product->displayPricing(), array_flip(['price', 'compare_at_price', 'discount_percent'])));
        $this->assertSame(5000.0, $product->compareAtFor(['Size' => 'S', 'Colour' => 'Ink', 'Material' => 'Silk']));
        $this->assertNull($product->compareAtFor(['Size' => 'S']), 'No variant chosen yet.');
    }

    public function test_a_bag_line_shows_its_variants_was_price(): void
    {
        $product = $this->discountedVariantProduct();

        $line = $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', [
            'product_id' => $product->id,
            'options' => ['Size' => 'S', 'Colour' => 'Ink', 'Material' => 'Silk'],
        ])->assertSuccessful()->json('items.0');

        $this->assertSame(4000, $line['unit_price']);
        $this->assertSame(5000, $line['product']['compare_at_price']);
        $this->assertSame(20, $line['product']['discount_percent']);
    }

    public function test_a_variants_mrp_may_not_be_below_its_price_including_tax(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('MRP (₹)');
        $first = array_key_first($page->get('data.variants')); // M / Camel / Linen at ₹2,500

        $page->set("data.variants.{$first}.compare_at_price", 2400)
            ->call('save')
            ->assertHasFormErrors(["variants.{$first}.compare_at_price"]);

        $page->set("data.variants.{$first}.compare_at_price", 2999)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertEquals(2999, $product->fresh()->variants[0]['compare_at_price']);
    }

    public function test_the_product_page_shows_the_products_own_saving_until_a_variant_is_chosen(): void
    {
        $product = $this->discountedVariantProduct();

        $this->withSession([Client::SESSION_KEY => 'alpha'])->get($product->url)
            ->assertSuccessful()
            ->assertSee(money(3000))
            ->assertSee('−17%')
            ->assertSee('variantPricing(', false);
    }

    public static function templatesWithAProductPage(): array
    {
        // Text only that template's product page has, so each run is known to use its view.
        return [
            'grocery' => ['grocery', 'You save'],
            'plumbing' => ['plumbing', '% off'],
            'heritage' => ['heritage', 'Select Pack Size'],
        ];
    }

    #[DataProvider('templatesWithAProductPage')]
    public function test_every_template_renders_a_variant_products_saving(string $template, string $marker): void
    {
        $this->client->storefront->update(['template' => $template]);
        $product = $this->discountedVariantProduct();
        $product->update(['template' => $template]);

        $html = $this->withSession([Client::SESSION_KEY => 'alpha'])->get($product->fresh()->url)
            ->assertSuccessful()
            ->getContent();

        $this->assertStringContainsString($marker, $html);
        $this->assertStringContainsString('variantPricing(', $html);
        $this->assertStringContainsString(e(money(3000)), $html, 'The product\'s own "was" price until a variant is chosen.');
    }

    public function test_cards_and_quick_view_show_the_cheapest_variants_saving(): void
    {
        $product = $this->discountedVariantProduct();
        $card = $product->toCard();

        $this->assertSame(2500.0, $card['price']);
        $this->assertSame(money(3000), $card['compare_at_price_formatted']);
        $this->assertSame(17, $card['discount_percent']);
        $this->assertSame(2500.0, $product->display_price);
        $this->assertTrue($product->is_on_sale);

        // Out of stock is skipped while another variant is in stock: S / Ink / Silk, ₹4,000 was ₹5,000.
        $variants = $product->variants;
        $variants[0]['stock'] = 0;
        $product->update(['variants' => $variants]);
        $this->assertSame(['price' => 4000.0, 'discount_percent' => 20], array_intersect_key($product->fresh()->toCard(), array_flip(['price', 'discount_percent'])));

        // The quick view loads the same card data.
        $this->withSession([Client::SESSION_KEY => 'alpha'])->getJson('/api/products/'.$product->slug)
            ->assertSuccessful()
            ->assertJsonFragment(['compare_at_price_formatted' => money(5000), 'discount_percent' => 20]);
    }

    public function test_the_stock_field_shows_the_variants_total_read_only(): void
    {
        $product = $this->editableVariantProduct();
        $this->actAsClientAdmin();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormFieldIsDisabled('variant_stock_total')
            ->assertFormFieldIsHidden('stock')
            ->assertSee('Total of the variants (step 2 below).');
    }

    public function test_the_products_mrp_may_not_be_below_its_price_including_tax(): void
    {
        $product = $this->editableVariantProduct(); // default price ₹2,000
        $this->actAsClientAdmin();

        $page = Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()]);

        $page->set('data.compare_at_price', 100)->call('save')->assertHasFormErrors(['compare_at_price']);
        // An MRP equal to the price is allowed: it simply shows no discount.
        $page->set('data.compare_at_price', 2000)->call('save')->assertHasNoFormErrors();
        $page->set('data.compare_at_price', null)->call('save')->assertHasNoFormErrors();
        $page->set('data.compare_at_price', 2400)->call('save')->assertHasNoFormErrors();
        $this->assertEquals(2400, $product->fresh()->compare_at_price);
    }

    public function test_a_colour_value_written_as_a_colour_code_gets_that_swatch(): void
    {
        $this->assertSame('#cc6363', ColourNames::hex('#cc6363'));
        $this->assertSame('#cc6363', ColourNames::hex('CC6363'));
        $this->assertSame('#cc6666', ColourNames::hex('#c66'));
        $this->assertNull(ColourNames::hex('123'));
        $this->assertNull(ColourNames::hex('#zzz'));

        $product = $this->product([
            'has_variants' => false,
            'variant_attributes' => [['name' => 'Colour', 'values' => ['Red', '#cc6363']]],
        ]);
        $this->assertSame(['Red' => '#d32f2f', '#cc6363' => '#cc6363'], $product->variantAttributes()[0]['swatches']);
    }

    public function test_the_cart_says_when_a_quantity_is_over_the_max_per_order_or_the_stock(): void
    {
        $post = fn (array $payload) => $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', $payload);

        // The product's own "max quantity per order".
        $limited = $this->product(['slug' => 'limited', 'sku' => 'LIM-1', 'stock' => 50, 'max_qty' => 5]);
        $post(['product_id' => $limited->id, 'qty' => 6])->assertUnprocessable()
            ->assertJson(['message' => 'You can order at most 5 of this item.']);
        $cart = $post(['product_id' => $limited->id, 'qty' => 4])->assertSuccessful();
        $post(['product_id' => $limited->id, 'qty' => 2])->assertUnprocessable()
            ->assertJson(['message' => 'You can order at most 5 of this item. You already have 4 in your cart.']);
        $this->withSession([Client::SESSION_KEY => 'alpha'])->patchJson('/cart/items/'.$cart->json('items.0.key'), ['qty' => 9])
            ->assertUnprocessable()->assertJson(['message' => 'You can order at most 5 of this item.']);
        $this->withSession([Client::SESSION_KEY => 'alpha'])->patchJson('/cart/items/'.$cart->json('items.0.key'), ['qty' => 5])->assertSuccessful();

        // The stock of the chosen variant (M / Camel / Linen has 3).
        $variant = $this->variantProduct();
        $post(['product_id' => $variant->id, 'options' => ['Size' => 'M', 'Colour' => 'Camel', 'Material' => 'Linen'], 'qty' => 4])
            ->assertUnprocessable()->assertJson(['message' => 'Only 3 left in stock.']);
    }
}
