<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Storefront;
use App\Support\ReviewModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reviews from the storefront: a verified buyer's review goes live at once, anything with a
 * link, email or phone number waits, everyone else waits, and the store can switch
 * auto-publishing off. The star rating never decides.
 */
class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Product $product;

    private Customer $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $own = ['client_id' => $this->client->id, 'template' => 'fashion'];
        $category = Category::create($own + ['name' => 'Sarees', 'slug' => 'sarees', 'is_active' => true]);
        $this->product = Product::create($own + ['name' => 'Banarasi Saree', 'slug' => 'banarasi-saree', 'category_id' => $category->id, 'price' => 18900, 'stock' => 10, 'is_active' => true, 'images' => []]);

        $this->buyer = $this->customer('buyer@example.com');
        $this->orderFor($this->buyer, 'delivered');
    }

    private function customer(string $email): Customer
    {
        return Customer::create(['client_id' => $this->client->id, 'name' => 'Riya Mehta', 'email' => $email, 'password' => 'password123', 'is_active' => true]);
    }

    private function orderFor(Customer $customer, string $status): Order
    {
        $order = Order::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'customer_id' => $customer->id, 'number' => 'EF-'.uniqid(),
            'email' => $customer->email, 'status' => $status, 'shipping_name' => 'Riya', 'shipping_line1' => '7 Carter Road',
            'shipping_city' => 'Mumbai', 'shipping_state' => 'MH', 'shipping_postal_code' => '400050', 'shipping_country' => 'India',
            'subtotal' => 18900, 'total' => 18900,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $this->product->id, 'name' => $this->product->name, 'price' => 18900, 'qty' => 1, 'total' => 18900]);

        return $order;
    }

    /** Submit a review on the storefront, signed in as $customer (or as a guest), and return it. */
    private function submit(?Customer $customer, array $overrides = []): Review
    {
        $request = $this->withSession([Client::SESSION_KEY => 'alpha']);
        if ($customer) {
            $request = $request->actingAs($customer, 'web');
        }
        $request->post('/product/banarasi-saree/reviews', $overrides + [
            'name' => 'Riya', 'rating' => 5, 'title' => 'Lovely drape', 'body' => 'Beautiful weave and colour, exactly as pictured.',
        ])->assertRedirect();

        return Review::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_a_verified_buyers_review_goes_live_with_a_badge(): void
    {
        $review = $this->submit($this->buyer);

        $this->assertTrue($review->is_verified_purchase);
        $this->assertTrue($review->is_approved);
        $this->assertNull($review->held_reason);
        $this->assertSame(1, $this->product->fresh()->review_count);
    }

    public function test_a_one_star_review_from_a_verified_buyer_is_published_like_any_other(): void
    {
        $review = $this->submit($this->buyer, ['rating' => 1, 'body' => 'The colour faded after one wash, disappointed.']);

        $this->assertTrue($review->is_approved);
    }

    public function test_links_emails_and_phone_numbers_are_held_even_from_a_verified_buyer(): void
    {
        foreach ([
            'Cheaper copies at www.fake-sarees.com, check them out' => ReviewModeration::HOLD_LINK,
            'See https://example.org for my styling tips with it' => ReviewModeration::HOLD_LINK,
            'Mail me at riya.styles@gmail.com for the blouse design' => ReviewModeration::HOLD_EMAIL,
            'WhatsApp me on +91 98123 45678 for resale enquiries' => ReviewModeration::HOLD_PHONE,
        ] as $body => $reason) {
            $review = $this->submit($this->buyer, ['body' => $body]);

            $this->assertFalse($review->is_approved, $body);
            $this->assertTrue($review->is_verified_purchase, $body);
            $this->assertSame($reason, $review->held_reason, $body);
        }
    }

    public function test_ordinary_numbers_do_not_count_as_a_phone_number(): void
    {
        $review = $this->submit($this->buyer, ['body' => 'Wore it to 3 weddings in 2026, 10/10 would buy again.']);

        $this->assertTrue($review->is_approved);
    }

    public function test_a_customer_who_has_not_received_the_product_waits_for_approval(): void
    {
        $waiting = $this->customer('waiting@example.com');
        $this->orderFor($waiting, 'shipped');

        $review = $this->submit($waiting);

        $this->assertFalse($review->is_verified_purchase);
        $this->assertFalse($review->is_approved);
        $this->assertSame(ReviewModeration::HOLD_NOT_VERIFIED, $review->held_reason);
        $this->assertSame(0, $this->product->fresh()->review_count);
    }

    public function test_a_guest_review_waits_for_approval(): void
    {
        $review = $this->submit(null);

        $this->assertFalse($review->is_approved);
        $this->assertSame(ReviewModeration::HOLD_GUEST, $review->held_reason);
    }

    public function test_with_auto_publishing_off_every_review_waits_but_keeps_its_badge(): void
    {
        Setting::create(['key' => 'client:'.$this->client->id.':reviews', 'value' => ['auto_publish_verified' => false]]);

        $review = $this->submit($this->buyer);

        $this->assertTrue($review->is_verified_purchase);
        $this->assertFalse($review->is_approved);
        $this->assertNull($review->held_reason);
    }

    public function test_the_product_page_shows_the_badge_only_on_verified_reviews(): void
    {
        $own = ['client_id' => $this->client->id, 'template' => 'fashion', 'product_id' => $this->product->id, 'rating' => 5, 'is_approved' => true];
        Review::create($own + ['name' => 'Verified Riya', 'title' => 'Verified one', 'body' => 'Bought and loved it.', 'is_verified_purchase' => true]);
        Review::create($own + ['name' => 'Imported Kavya', 'title' => 'Imported one', 'body' => 'Added by the store.', 'is_verified_purchase' => false]);

        $html = $this->withSession([Client::SESSION_KEY => 'alpha'])->get('/product/banarasi-saree')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Verified purchase'));
        $this->assertMatchesRegularExpression('/Verified Riya\s*<span[^>]*>✓ Verified purchase/', $html);
    }

    public function test_the_admin_list_filters_by_rating(): void
    {
        $own = ['client_id' => $this->client->id, 'template' => 'fashion', 'product_id' => $this->product->id, 'is_approved' => true, 'body' => 'Review text here.'];
        foreach ([5 => 'Five-star review', 4 => 'Four-star review', 2 => 'Two-star review', 1 => 'One-star review'] as $rating => $title) {
            Review::create($own + ['name' => 'Shopper', 'rating' => $rating, 'title' => $title]);
        }
        $admin = \App\Models\ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => \App\Models\ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        $html = $this->actingAs($admin, 'client')
            ->get('/client/reviews?filters[rating][values][0]=1&filters[rating][values][1]=2')
            ->assertOk()->getContent();

        $this->assertStringContainsString('One-star review', $html);
        $this->assertStringContainsString('Two-star review', $html);
        $this->assertStringNotContainsString('Four-star review', $html);
        $this->assertStringNotContainsString('Five-star review', $html);
        $this->assertStringContainsString('fi-ta-toolbar-filter-indicators', $html);
        // "Apply" closes the filters dropdown.
        $this->assertMatchesRegularExpression('/wire:click="applyTableFilters"[^>]*x-on:click="close\(\)"|x-on:click="close\(\)"[^>]*wire:click="applyTableFilters"/', $html);
    }
}
