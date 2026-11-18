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
 * A shopper without an account proves the phone number with a WhatsApp code at checkout; that
 * makes (or finds) the account for the number. Guest records made by email alone (older orders,
 * bookings) stay as they are, and are claimed only through the emailed link.
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

    private function guestCheckout(?string $email, string $name = 'Asha Rao', string $phone = '9999999999'): Order
    {
        $this->withSession([Client::SESSION_KEY => 'alpha'])
            ->postJson('/cart/items', ['product_id' => $this->product->id, 'options' => [], 'qty' => 1])
            ->assertSuccessful();

        $signedIn = auth('web')->check();
        $response = $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/checkout', [
            'email' => $email, 'phone' => $phone, 'shipping_name' => $name,
            'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
            'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'shipping_method' => 'standard', 'payment_method' => 'cod',
        ])->assertSessionHasNoErrors();
        if ($signedIn) {
            // A signed-in customer needs no code.
            $response->assertRedirect();
            $this->ordersBefore = Order::withoutGlobalScopes()->count();

            return Order::withoutGlobalScopes()->latest('id')->firstOrFail();
        }
        $response->assertRedirect(route('otp.verify'));

        $this->assertSame(0, Order::withoutGlobalScopes()->count() - $this->ordersBefore, 'Nothing is ordered before the code is entered.');
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertSessionHasNoErrors()->assertRedirect();
        $this->ordersBefore = Order::withoutGlobalScopes()->count();

        return Order::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    private int $ordersBefore = 0;

    /** Signs out between checkouts, as a different visit would be. */
    private function signOut(): void
    {
        auth('web')->logout();
    }

    public function test_email_is_optional_at_checkout_and_the_order_can_be_tracked_by_phone(): void
    {
        $order = $this->guestCheckout(null, phone: '+91 98765 43210');

        $this->assertNull($order->email);
        $this->assertSame('9876543210', $order->phone);
        $account = Customer::findOrFail($order->customer_id);
        $this->assertNull($account->email);
        $this->assertSame('9876543210', $account->phone);

        $this->withSession([Client::SESSION_KEY => 'alpha'])
            ->get('/track-order?number='.$order->number.'&contact=098765-43210')
            ->assertOk()->assertSee($order->number)->assertDontSee('We could not find an order');
        $this->withSession([Client::SESSION_KEY => 'alpha'])
            ->get('/track-order?number='.$order->number.'&contact=9000000000')
            ->assertOk()->assertSee('We could not find an order');
    }

    public function test_a_guest_checkout_makes_an_account_for_the_proven_phone(): void
    {
        $order = $this->guestCheckout('Asha@Example.test');

        $customer = Customer::where('client_id', $this->client->id)->sole();
        $this->assertSame('asha@example.test', $customer->email);
        $this->assertSame('Asha Rao', $customer->name);
        $this->assertSame('9999999999', $customer->phone);
        $this->assertNull($customer->password, 'They sign in with a WhatsApp code until they set one.');
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertTrue($customer->isAccount());
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertAuthenticatedAs($customer, 'web');
        $this->assertSame(1, $customer->addresses()->count(), 'The checkout address is saved to the new account.');
    }

    public function test_a_later_checkout_with_the_same_phone_uses_the_same_account(): void
    {
        $first = $this->guestCheckout('asha@example.test');
        $this->signOut();
        $second = $this->guestCheckout('other@example.test', 'Someone Else');

        $this->assertSame(1, Customer::where('client_id', $this->client->id)->count());
        $this->assertSame($first->customer_id, $second->customer_id);
        $this->assertSame('Asha Rao', Customer::find($first->customer_id)->name, 'A checkout never overwrites the account.');
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

    public function test_an_earlier_guest_record_keeps_its_email_and_is_not_claimed_by_phone(): void
    {
        $guest = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'password' => null, 'is_active' => true]);

        $order = $this->guestCheckout('asha@example.test', 'Asha', '9876543210');

        // Proving a phone says nothing about the email: the guest's orders stay where they are.
        $this->assertNull($guest->fresh()->phone);
        $this->assertNotSame($guest->id, $order->customer_id);
        $this->assertNull(Customer::find($order->customer_id)->email);
        $this->assertSame('asha@example.test', $order->email);
    }

    public function test_a_guest_checkout_never_sets_the_phone_of_a_registered_account(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);

        $this->guestCheckout('asha@example.test', 'Asha', '9876543210');

        $this->assertNull($member->fresh()->phone);
    }

    public function test_a_guest_order_with_someone_elses_email_does_not_join_their_account(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);

        $order = $this->guestCheckout('asha@example.test');

        // The order belongs to the account of the proven phone, never to the one that only shares the email.
        $this->assertNotSame($member->id, $order->customer_id);
        $this->assertSame(2, Customer::count());
        $this->assertFalse(auth('web')->user()->is($member));
    }

    public function test_a_guest_checkout_with_a_registered_phone_joins_that_account(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9999999999', 'password' => 'password123', 'is_active' => true]);

        $order = $this->guestCheckout('asha@example.test');

        $this->assertSame($member->id, $order->customer_id);
        $this->assertSame(1, Customer::count());
        $this->assertAuthenticatedAs($member->fresh(), 'web');
    }

    public function test_registering_with_a_guest_email_emails_a_set_password_link_instead(): void
    {
        Notification::fake();
        // A guest record made by email alone (an older order or a booking).
        $guest = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'phone' => '9999999999', 'is_active' => true]);

        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/register', [
            'name' => 'Asha Rao', 'email' => 'asha@example.test', 'phone' => '9876543210',
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
            'name' => 'New Buyer', 'email' => 'new@example.test', 'phone' => '9876543210',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('otp.verify'));
        $this->assertGuest('web');
        $this->assertSame(0, Customer::count(), 'The account waits for the WhatsApp code.');

        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('account.index'));

        $this->assertAuthenticated('web');
        $this->assertNotNull(Customer::sole()->phone_verified_at);
    }

    public function test_a_registered_email_is_still_taken(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);

        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/register', [
            'name' => 'Asha Rao', 'email' => 'asha@example.test', 'phone' => '9876543210',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ])->assertSessionHasErrors('email');
    }

    public function test_an_account_can_be_made_without_an_email(): void
    {
        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/register', [
            'name' => 'Phone Only', 'phone' => '9123456780', 'email' => '',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('otp.verify'));
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('account.index'));

        $this->assertAuthenticated('web');
        $this->assertNull(Customer::where('phone', '9123456780')->sole()->email);
    }

    public function test_a_registered_phone_is_taken_and_signs_in(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'phone' => '9876543210', 'password' => 'password123', 'is_active' => true]);

        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/register', [
            'name' => 'Other Buyer', 'email' => 'other@example.test', 'phone' => '+91 98765 43210',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ])->assertSessionHasErrors('phone');

        // A passwordless guest record with the same phone does not get in the way of signing in.
        Customer::create(['client_id' => $this->client->id, 'name' => 'Guest', 'email' => 'guest@example.test', 'phone' => '9876543210', 'is_active' => true]);
        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/login', ['phone' => '98765 43210', 'password' => 'password123'])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticated('web');
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
