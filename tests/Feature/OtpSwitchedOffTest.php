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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * With WhatsApp off (services.otp.enabled false) no flow waits for a code that cannot arrive:
 * sign-up makes the account at once with the phone unverified, a guest checks out as a guest,
 * the sign-in with a code is hidden, and Forgot password emails a reset link.
 */
class OtpSwitchedOffTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.otp.enabled' => false]);

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

    private function onStore(): static
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha']);
    }

    private function checkout(array $overrides = [])
    {
        $this->onStore()->postJson('/cart/items', ['product_id' => $this->product->id, 'options' => [], 'qty' => 2])->assertSuccessful();

        return $this->onStore()->post('/checkout', $overrides + [
            'email' => 'asha@example.test', 'phone' => '9876543210', 'shipping_name' => 'Asha Rao',
            'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
            'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'shipping_method' => 'standard', 'payment_method' => 'cod',
        ]);
    }

    public function test_the_switch_follows_whatsapp_unless_set_on_its_own(): void
    {
        // config/services.php: 'enabled' => env('OTP_ENABLED', env('WHATSAPP_ENABLED', false)).
        $config = file_get_contents(config_path('services.php'));
        $this->assertStringContainsString("env('OTP_ENABLED', env('WHATSAPP_ENABLED', false))", $config);
    }

    public function test_signing_up_makes_the_account_at_once_with_the_phone_unverified(): void
    {
        $this->onStore()->post('/register', [
            'name' => 'Asha Rao', 'email' => 'asha@example.test', 'phone' => '+91 98765 43210',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('account.index'));

        $customer = Customer::sole();
        $this->assertNull($this->whatsapp->lastCode(), 'No code is sent.');
        $this->assertNull($customer->phone_verified_at);
        $this->assertTrue(Hash::check('Str0ng-pass-123', $customer->password));
        $this->assertSame('asha@example.test', $customer->email);
        $this->assertAuthenticatedAs($customer, 'web');
    }

    public function test_a_number_that_already_has_an_account_cannot_sign_up_again(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Owner', 'phone' => '9876543210', 'password' => 'old-pass-1', 'is_active' => true]);

        $this->onStore()->post('/register', [
            'name' => 'Someone Else', 'phone' => '+91 98765 43210',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ])->assertSessionHasErrors('phone');
        $this->assertSame(1, Customer::count());
        $this->assertGuest('web');
    }

    public function test_a_guest_checkout_places_the_order_as_a_guest_without_a_code(): void
    {
        $response = $this->checkout();
        $order = Order::withoutGlobalScopes()->sole();
        $response->assertRedirect(route('orders.confirmation', $order->number));
        $this->assertNull($this->whatsapp->lastCode());

        // Kept against a guest record for the email; nobody is signed in.
        $guest = Customer::sole();
        $this->assertSame($guest->id, $order->customer_id);
        $this->assertFalse($guest->isAccount());
        $this->assertGuest('web');
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->onStore()->get(route('orders.confirmation', $order->number))->assertOk();
    }

    public function test_a_guest_using_an_accounts_number_is_not_signed_in_to_it(): void
    {
        $owner = Customer::create(['client_id' => $this->client->id, 'name' => 'Owner', 'email' => 'owner@example.test', 'phone' => '9876543210', 'password' => 'old-pass-1', 'is_active' => true]);

        $this->checkout(['email' => 'someone@example.test']);

        $order = Order::withoutGlobalScopes()->sole();
        $this->assertNotSame($owner->id, $order->customer_id);
        $this->assertGuest('web');
    }

    public function test_the_checkout_and_sign_in_pages_do_not_mention_codes(): void
    {
        $this->onStore()->get('/login')->assertOk()->assertDontSee(route('login.otp'), false)->assertDontSee('WhatsApp code');
        $this->onStore()->get('/forgot-password')->assertOk()->assertSee('email you a link')->assertSee('Email me a reset link')->assertDontSee('Send code');

        $this->onStore()->postJson('/cart/items', ['product_id' => $this->product->id, 'options' => [], 'qty' => 1])->assertSuccessful();
        $this->onStore()->get('/checkout')->assertOk()->assertDontSee('We will send a code on WhatsApp');
    }

    public function test_the_sign_in_with_a_code_goes_to_the_password_sign_in(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'password' => 'old-pass-1', 'is_active' => true]);

        $this->onStore()->get('/login/otp')->assertRedirect(route('login'));
        $this->onStore()->post('/login/otp', ['phone' => '9876543210'])->assertRedirect(route('login'));
        $this->assertNull($this->whatsapp->lastCode());
    }

    public function test_forgot_password_emails_a_reset_link_to_the_accounts_address(): void
    {
        Notification::fake();
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'phone' => '9876543210', 'password' => 'old-pass-1', 'is_active' => true]);

        $this->onStore()->from('/forgot-password')->post('/forgot-password', ['phone' => '98765 43210'])
            ->assertRedirect('/forgot-password')->assertSessionHas('status', 'We have emailed a link to reset your password to a•••@example.test.');
        Notification::assertSentTo($member, ResetPassword::class);
        $this->assertNull($this->whatsapp->lastCode());
    }

    public function test_forgot_password_without_an_email_says_to_contact_the_store(): void
    {
        Notification::fake();
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'password' => 'old-pass-1', 'is_active' => true]);

        $this->onStore()->post('/forgot-password', ['phone' => '98765 43210'])->assertSessionHasErrors(['phone' => 'This account has no email address to send a reset link to. Please contact the store to reset your password.']);
        Notification::assertNothingSent();
    }

    public static function templates(): array
    {
        return [['fashion'], ['grocery'], ['heritage'], ['plumbing'], ['plumbing-services'], ['bulk']];
    }

    #[DataProvider('templates')]
    public function test_no_template_offers_the_code_sign_in(string $template): void
    {
        Storefront::where('id', $this->client->storefront_id)->update(['template' => $template]);

        $this->onStore()->get('/login')->assertOk()->assertDontSee(route('login.otp'), false);
        $this->onStore()->get('/forgot-password')->assertOk()->assertSee('Email me a reset link');
    }
}
