<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A guest's order is kept against a customer record for their email (created without a
 * password), and that person gets into the account only through the emailed link.
 */
class GuestCustomerTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Steel Tiffin',
            'slug' => 'steel-tiffin', 'sku' => 'TIF-1', 'price' => 1000, 'stock' => 10, 'is_active' => true,
        ]);
        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => [
            'shipping_methods' => [['code' => 'standard', 'name' => 'Standard', 'cost' => 0]],
        ] + config('client_defaults.checkout')]);
        Cache::forget('settings.all');
    }

    private function guestCheckout(string $email, string $name = 'Asha Rao', string $phone = '9999999999'): Order
    {
        $this->withSession([Client::SESSION_KEY => 'alpha'])
            ->postJson('/cart/items', ['product_id' => $this->product->id, 'options' => [], 'qty' => 1])
            ->assertSuccessful();

        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/checkout', [
            'email' => $email, 'phone' => $phone, 'shipping_name' => $name,
            'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
            'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'shipping_method' => 'standard', 'payment_method' => 'cod',
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Order::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public function test_a_guest_order_creates_a_guest_customer_for_the_email(): void
    {
        $order = $this->guestCheckout('Asha@Example.test');

        $customer = Customer::where('client_id', $this->client->id)->sole();
        $this->assertSame('asha@example.test', $customer->email);
        $this->assertSame('Asha Rao', $customer->name);
        $this->assertSame('9999999999', $customer->phone);
        $this->assertNull($customer->password, 'A guest has no password until they prove the address.');
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertGuest('web');
    }

    public function test_later_guest_orders_with_the_same_email_reuse_the_customer(): void
    {
        $first = $this->guestCheckout('asha@example.test');
        $second = $this->guestCheckout('ASHA@example.test', 'Someone Else');

        $this->assertSame(1, Customer::where('client_id', $this->client->id)->count());
        $this->assertSame($first->customer_id, $second->customer_id);
        $this->assertSame('Asha Rao', Customer::find($first->customer_id)->name, 'A guest never overwrites the record.');
    }

    public function test_a_signed_in_customer_without_a_phone_gets_the_checkout_phone(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($member, 'web');

        $this->guestCheckout('asha@example.test', 'Asha', '9876543210');
        $this->assertSame('9876543210', $member->fresh()->phone);

        // A saved number is never replaced by a later checkout.
        $this->guestCheckout('asha@example.test', 'Asha', '9123456780');
        $this->assertSame('9876543210', $member->fresh()->phone);
    }

    public function test_an_earlier_guest_without_a_phone_gets_it_from_the_next_order(): void
    {
        $guest = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'password' => null, 'is_active' => true]);

        $this->guestCheckout('asha@example.test', 'Asha', '9876543210');

        $this->assertSame('9876543210', $guest->fresh()->phone);
    }

    public function test_a_guest_checkout_never_sets_the_phone_of_a_registered_account(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);

        $this->guestCheckout('asha@example.test', 'Asha', '9876543210');

        $this->assertNull($member->fresh()->phone);
    }

    public function test_a_guest_order_with_a_registered_email_joins_that_account(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);

        $order = $this->guestCheckout('asha@example.test');

        $this->assertSame($member->id, $order->customer_id);
        $this->assertSame(1, Customer::count());
        $this->assertGuest('web');
    }

    public function test_registering_with_a_guest_email_emails_a_set_password_link_instead(): void
    {
        Notification::fake();
        $this->guestCheckout('asha@example.test');
        $guest = Customer::sole();

        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/register', [
            'name' => 'Asha Rao', 'email' => 'asha@example.test',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

        // Not signed in and no password set: whoever typed the address must open the email first.
        $this->assertGuest('web');
        $this->assertNull($guest->fresh()->password);
        $this->assertSame(1, Customer::count());
        Notification::assertSentTo($guest, ResetPassword::class);
    }

    public function test_registering_a_new_email_still_creates_and_signs_in(): void
    {
        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/register', [
            'name' => 'New Buyer', 'email' => 'new@example.test',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('account.index'));

        $this->assertAuthenticated('web');
    }

    public function test_a_registered_email_is_still_taken(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);

        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/register', [
            'name' => 'Asha Rao', 'email' => 'asha@example.test',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ])->assertSessionHasErrors('email');
    }

    public function test_the_command_links_earlier_guest_orders(): void
    {
        $order = $this->guestCheckout('asha@example.test');
        Order::withoutGlobalScopes()->whereKey($order->id)->update(['customer_id' => null]);
        Customer::query()->forceDelete();

        $this->artisan('customers:link-guests')->assertSuccessful();

        $customer = Customer::where('client_id', $this->client->id)->sole();
        $this->assertNull($customer->password);
        $this->assertSame($customer->id, $order->fresh()->customer_id);

        // Running it again changes nothing.
        $this->artisan('customers:link-guests')->assertSuccessful();
        $this->assertSame(1, Customer::count());
    }
}
