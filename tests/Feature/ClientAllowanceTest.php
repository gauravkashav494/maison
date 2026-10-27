<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Clients\ClientQuota;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Rules\WithinClientImageLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Per-client allowances: 50 product images by default, configurable per client or per plan,
 * plus the product count limit that already existed.
 *
 * The allowance is counted from the products themselves, so it cannot drift, and it is
 * enforced by a validation rule (the back end) as well as being shown in the form and on the
 * dashboard (the front end).
 */
class ClientAllowanceTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'slug' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $this->plan->id, 'is_active' => true]);
    }

    private function product(array $images, string $slug = 'p1'): Product
    {
        return Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id,
            'template' => 'fashion',
            'name' => 'Product '.$slug,
            'slug' => $slug,
            'price' => 1000,
            'stock' => 5,
            'is_active' => true,
            'images' => $images,
        ]);
    }

    public function test_the_default_allowance_is_fifty_images_per_client(): void
    {
        $this->assertSame(50, $this->client->imageLimit());
        $this->assertSame(50, config('clients.default_image_limit'));
    }

    public function test_a_client_or_its_plan_can_change_the_allowance(): void
    {
        // The plan is the default for everyone on it.
        $this->plan->update(['image_limit' => 200]);
        $this->assertSame(200, $this->client->fresh()->imageLimit());

        // The client's own value wins, which is how a paid plan is given more.
        $this->client->update(['image_limit' => 500]);
        $this->assertSame(500, $this->client->fresh()->imageLimit());

        // A client with no plan and no value of its own falls back to the platform default.
        $other = Client::create(['name' => 'Beta', 'slug' => 'beta', 'storefront_id' => $this->client->storefront_id, 'is_active' => true]);
        $this->assertSame(50, $other->imageLimit());
    }

    public function test_images_are_counted_per_client_and_never_twice(): void
    {
        $this->product(['a.jpg', 'b.jpg'], 'one');
        $this->product(['b.jpg', 'c.jpg'], 'two');          // b.jpg is the same file
        $this->product(['d.jpg', 'd.jpg'], 'three');        // listed twice on one product

        $quota = ClientQuota::for($this->client);
        $quota->forget();

        $this->assertSame(4, $quota->imageCount(), 'a, b, c and d — each counted once.');

        // Another client's images are not counted against this one.
        $other = Client::create(['name' => 'Beta', 'slug' => 'beta', 'storefront_id' => $this->client->storefront_id, 'is_active' => true]);
        Product::withoutGlobalScopes()->create([
            'client_id' => $other->id, 'template' => 'fashion', 'name' => 'Theirs', 'slug' => 'theirs',
            'price' => 1, 'stock' => 1, 'images' => ['x.jpg', 'y.jpg', 'z.jpg'],
        ]);

        $quota->forget();
        $this->assertSame(4, $quota->imageCount());
        $this->assertSame(3, ClientQuota::for($other)->imageCount());
    }

    public function test_variant_images_count_towards_the_allowance(): void
    {
        $product = $this->product(['a.jpg'], 'one');
        $product->update([
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['S']]],
            'variants' => [['options' => ['Size' => 'S'], 'images' => ['v1.jpg', 'v2.jpg']]],
        ]);

        $quota = ClientQuota::for($this->client);
        $quota->forget();

        $this->assertSame(3, $quota->imageCount());
    }

    public function test_a_save_that_would_pass_the_allowance_is_rejected(): void
    {
        $this->client->update(['image_limit' => 3]);
        $this->product(['a.jpg', 'b.jpg'], 'one');
        app(ClientContext::class)->resolveForPublic($this->client->fresh());
        ClientQuota::for($this->client->fresh())->forget();

        $validate = fn (array $images, array $replacing = []) => Validator::make(
            ['images' => $images],
            ['images' => [new WithinClientImageLimit($replacing)]],
        );

        // Two in use, one left: one more image is fine, two are not.
        $this->assertTrue($validate(['c.jpg'])->passes());
        $this->assertFalse($validate(['c.jpg', 'd.jpg'])->passes());
        $this->assertStringContainsString('limited to 3 product images', $validate(['c.jpg', 'd.jpg'])->errors()->first('images'));

        // Replacing images frees their slots, so swapping does not hit the limit.
        $this->assertTrue($validate(['c.jpg', 'd.jpg'], ['a.jpg', 'b.jpg'])->passes());

        // An image already counted does not cost a second slot.
        $this->assertTrue($validate(['a.jpg', 'b.jpg', 'c.jpg'], ['a.jpg', 'b.jpg'])->passes());
    }

    public function test_a_client_already_over_the_allowance_can_still_edit_its_products(): void
    {
        // Seeded or imported catalogues can arrive over the limit, and a limit can be lowered
        // after the fact. Either way the client must not be locked out of its own products.
        $this->product(['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'], 'one');
        $this->client->update(['image_limit' => 2]);
        app(ClientContext::class)->resolveForPublic($this->client->fresh());
        ClientQuota::for($this->client->fresh())->forget();

        $validate = fn (array $images, array $replacing = []) => Validator::make(
            ['images' => $images],
            ['images' => [new WithinClientImageLimit($replacing)]],
        );

        // Saving the product as it stands changes nothing, so it is allowed.
        $this->assertTrue($validate(['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'], ['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'])->passes());

        // Removing one is allowed too — it moves towards the limit.
        $this->assertTrue($validate(['a.jpg', 'b.jpg'], ['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'])->passes());

        // Adding another is still refused: the count may not grow while over the limit.
        $this->assertFalse($validate(['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg', 'e.jpg'], ['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg'])->passes());
    }

    public function test_zero_means_unlimited_and_rejects_nothing(): void
    {
        // Zero is how a plan (or one client) lifts the cap; a blank value inherits instead.
        $this->client->update(['image_limit' => 0]);
        $this->assertNull($this->client->fresh()->imageLimit());

        app(ClientContext::class)->resolveForPublic($this->client->fresh());
        ClientQuota::for($this->client->fresh())->forget();

        $passes = Validator::make(
            ['images' => array_map(fn ($i) => "img{$i}.jpg", range(1, 60))],
            ['images' => [new WithinClientImageLimit]],
        )->passes();

        $this->assertTrue($passes);

        // The same through the plan, for everyone on it.
        $this->client->update(['image_limit' => null]);
        $this->plan->update(['image_limit' => 0]);
        $this->assertNull($this->client->fresh()->imageLimit());
    }

    public function test_the_product_limit_stops_a_client_adding_more(): void
    {
        $this->client->update(['product_limit' => 2]);
        $staff = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        app(ClientContext::class)->resolveForIdentity($staff);
        app(\App\Admin\StoreContext::class)->resolve($staff, $this->client->templateId());

        $this->product(['a.jpg'], 'one');
        $this->assertTrue($staff->can('create', Product::class));

        $this->product(['b.jpg'], 'two');
        $this->assertFalse($staff->can('create', Product::class), 'At the limit, the create action is refused.');
    }
}
