<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\ClientQuota;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Photos uploaded under a colour (Variants → Colour → Browse) join the product's images on
 * save, so they count against the same limits as the Media tab: the account's image allowance
 * and the 12 images one product can hold.
 */
class ProductImageLimitTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function signIn(int $imageLimit): void
    {
        $plan = Plan::create(['slug' => 'tiny', 'name' => 'Tiny', 'price_label' => '₹X', 'image_limit' => $imageLimit, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->category = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'shirts', 'is_active' => true]);

        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    /** @param array<int, string> $gallery */
    private function form(array $gallery, int $colourUploads): array
    {
        $files = array_map(fn (int $i) => UploadedFile::fake()->image("red-{$i}.jpg"), range(1, $colourUploads));

        return [
            'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'category_level_0' => $this->category->id, 'price_excl_tax' => 1000,
            'images' => $gallery,
            'variant_attributes' => [['preset' => 'Colour', 'name' => 'Colour', 'values' => ['Red'], 'uploads' => ['Red' => $files]]],
        ];
    }

    public function test_colour_uploads_count_against_the_account_image_allowance(): void
    {
        $this->signIn(imageLimit: 2);

        Livewire::test(CreateProduct::class)
            ->fillForm($this->form([], colourUploads: 3))
            ->call('create')
            ->assertHasFormErrors(['images']);
    }

    public function test_colour_uploads_count_towards_the_twelve_images_per_product(): void
    {
        $this->signIn(imageLimit: 100);
        $gallery = array_map(fn (int $i) => "clients/1/products/photo-{$i}.jpg", range(1, 12));

        Livewire::test(CreateProduct::class)
            ->fillForm($this->form($gallery, colourUploads: 1))
            ->call('create')
            ->assertHasFormErrors(['images']);
    }

    public function test_colour_uploads_within_both_limits_save(): void
    {
        $this->signIn(imageLimit: 100);

        Livewire::test(CreateProduct::class)
            ->fillForm($this->form([], colourUploads: 2))
            ->call('create')
            ->assertHasNoFormErrors();

        // The colour's photos joined the gallery and are picked for that colour.
        $product = Product::withoutGlobalScopes()->where('slug', 'linen-shirt')->firstOrFail();
        $this->assertCount(2, $product->images);
        $this->assertSame($product->images, $product->variant_attributes[0]['images']['Red']);
    }

    public function test_gallery_uploads_alone_still_count_against_the_allowance(): void
    {
        $this->signIn(imageLimit: 2);
        $form = $this->form([], colourUploads: 1);
        $form['images'] = array_map(fn (int $i) => UploadedFile::fake()->image("photo-{$i}.jpg"), range(1, 3));
        $form['variant_attributes'] = [];

        Livewire::test(CreateProduct::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasFormErrors(['images']);
    }

    public function test_editing_a_product_at_the_limit_without_new_photos_still_saves(): void
    {
        $this->signIn(imageLimit: 2);
        $product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $this->category->id,
            'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'price' => 1000, 'stock' => 5, 'is_active' => true,
            'images' => ['clients/1/products/a.jpg', 'clients/1/products/b.jpg'],
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Linen Shirt II'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Linen Shirt II', $product->fresh()->name);
    }

    public function test_removing_an_image_lowers_the_count_at_once(): void
    {
        $this->signIn(imageLimit: 3);
        $product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $this->category->id,
            'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'price' => 1000, 'stock' => 5, 'is_active' => true,
            'images' => ['clients/1/products/a.jpg', 'clients/1/products/b.jpg', 'clients/1/products/c.jpg'],
        ]);
        $this->assertSame(3, ClientQuota::current()->imageCount()); // read once, so it is cached

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['images' => ['clients/1/products/a.jpg', 'clients/1/products/b.jpg']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, ClientQuota::current()->imageCount());
    }
}
