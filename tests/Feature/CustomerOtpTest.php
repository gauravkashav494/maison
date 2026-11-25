<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use App\Services\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * WhatsApp codes on the storefront: sign-up and guest checkout wait for the code, a code lasts
 * five minutes and a few tries, and an account can sign in with a code instead of a password.
 */
class CustomerOtpTest extends TestCase
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
        Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Steel Tiffin',
            'slug' => 'steel-tiffin', 'sku' => 'TIF-1', 'price' => 1000, 'stock' => 10, 'is_active' => true,
        ]);
        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => [
            'shipping_methods' => [['code' => 'standard', 'name' => 'Standard', 'cost' => 0]],
        ] + config('client_defaults.checkout')]);
        Cache::forget('settings.all');
    }

    private function onStore(string $subdomain = 'alpha'): static
    {
        return $this->withSession([Client::SESSION_KEY => $subdomain]);
    }

    private function register(array $overrides = [])
    {
        return $this->onStore()->post('/register', $overrides + [
            'name' => 'Asha Rao', 'email' => 'asha@example.test', 'phone' => '+91 98765 43210',
            'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
        ]);
    }

    private function checkout()
    {
        $this->onStore()->postJson('/cart/items', ['product_id' => $this->product->id, 'options' => [], 'qty' => 2])->assertSuccessful();

        return $this->onStore()->post('/checkout', [
            'email' => 'asha@example.test', 'phone' => '9876543210', 'shipping_name' => 'Asha Rao',
            'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
            'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'shipping_method' => 'standard', 'payment_method' => 'cod',
        ]);
    }

    private function wrongCode(): string
    {
        return $this->whatsapp->lastCode() === '000000' ? '111111' : '000000';
    }

    // ---- Sign-up ---------------------------------------------------------------

    public function test_signing_up_sends_the_otp_template_to_the_phone(): void
    {
        $this->register()->assertSessionHasNoErrors()->assertRedirect(route('otp.verify'));

        $this->assertCount(1, $this->whatsapp->sent);
        $this->assertSame('9876543210', $this->whatsapp->sent[0]['phone']);
        $this->assertSame('otp', $this->whatsapp->sent[0]['template']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->whatsapp->lastCode());

        $this->onStore()->get('/verify')->assertOk()->assertSee('Verify your number')->assertSee('••••••3210')->assertSee('Code expires in')->assertSee('05:00');
        $this->assertSame(0, Customer::count());
    }

    public function test_the_account_is_made_with_the_chosen_password_once_the_code_is_entered(): void
    {
        $this->register();
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('account.index'));

        $customer = Customer::sole();
        $this->assertAuthenticatedAs($customer, 'web');
        $this->assertSame('9876543210', $customer->phone);
        $this->assertNotNull($customer->phone_verified_at);

        auth('web')->logout();
        $this->onStore()->post('/login', ['phone' => '9876543210', 'password' => 'Str0ng-pass-123'])->assertRedirect(route('account.index'));
    }

    public function test_a_wrong_code_is_refused_and_counts_down(): void
    {
        $this->register();

        $this->enterOtp($this->wrongCode(), [Client::SESSION_KEY => 'alpha'])->assertSessionHasErrors(['code' => 'That code is not correct. 4 attempts left.']);
        $this->assertSame(0, Customer::count());
        $this->assertGuest('web');
    }

    public function test_the_code_stops_working_after_five_wrong_attempts(): void
    {
        $this->register();
        $code = $this->whatsapp->lastCode();

        foreach (range(1, 5) as $_) {
            $this->enterOtp($this->wrongCode(), [Client::SESSION_KEY => 'alpha']);
        }

        $this->enterOtp($code, [Client::SESSION_KEY => 'alpha'])->assertSessionHasErrors('code');
        $this->assertSame(0, Customer::count());
    }

    public function test_the_code_expires_after_five_minutes(): void
    {
        $this->register();
        $code = $this->whatsapp->lastCode();

        $this->travel(301)->seconds();

        $this->enterOtp($code, [Client::SESSION_KEY => 'alpha'])->assertSessionHasErrors('code');
        $this->assertSame(0, Customer::count());
    }

    public function test_a_code_entered_within_five_minutes_works(): void
    {
        $this->register();
        $this->travel(290)->seconds();

        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('account.index'));
    }

    public function test_resend_waits_a_moment_then_sends_a_new_code_that_replaces_the_old_one(): void
    {
        $this->register();
        $first = $this->whatsapp->lastCode();

        $this->onStore()->post('/verify/resend')->assertSessionHasErrors('code');
        $this->assertCount(1, $this->whatsapp->sent);

        $this->travel(61)->seconds();
        $this->onStore()->post('/verify/resend')->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertCount(2, $this->whatsapp->sent);

        if ($first !== $this->whatsapp->lastCode()) {
            $this->enterOtp($first, [Client::SESSION_KEY => 'alpha'])->assertSessionHasErrors('code');
        }
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('account.index'));
    }

    public function test_one_phone_gets_a_limited_number_of_codes(): void
    {
        $this->register();
        foreach (range(1, 4) as $_) {
            $this->travel(61)->seconds();
            $this->onStore()->post('/verify/resend');
        }
        $this->assertCount(5, $this->whatsapp->sent);

        $this->travel(61)->seconds();
        $this->onStore()->post('/verify/resend')->assertSessionHasErrors('code');
        $this->assertCount(5, $this->whatsapp->sent);
    }

    public function test_when_whatsapp_fails_the_form_says_so_and_nothing_is_made(): void
    {
        $this->whatsapp->fails = true;

        $this->register()->assertSessionHasErrors('phone')->assertRedirect();
        $this->assertSame(0, Customer::count());
    }

    public function test_a_code_only_works_on_the_store_that_sent_it(): void
    {
        $this->register();

        $this->enterOtp(session: [Client::SESSION_KEY => 'beta'])->assertRedirect(route('login'));
        $this->assertSame(0, Customer::count());
    }

    public function test_the_verify_page_without_a_pending_code_goes_back_to_sign_in(): void
    {
        $this->onStore()->get('/verify')->assertRedirect(route('login'));
    }

    // ---- Checkout --------------------------------------------------------------

    public function test_a_guest_checkout_places_the_order_only_after_the_code(): void
    {
        $this->checkout()->assertSessionHasNoErrors()->assertRedirect(route('otp.verify'));
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
        $this->onStore()->get('/verify')->assertOk()->assertSee('Confirm your order')->assertSee('Verify &amp; place order', false);

        $this->enterOtp($this->wrongCode(), [Client::SESSION_KEY => 'alpha'])->assertSessionHasErrors('code');
        $this->assertSame(0, Order::withoutGlobalScopes()->count());

        $response = $this->enterOtp(session: [Client::SESSION_KEY => 'alpha']);
        $order = Order::withoutGlobalScopes()->sole();
        $response->assertRedirect(route('orders.confirmation', $order->number));

        $customer = Customer::sole();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(2, $order->items()->sole()->qty);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertAuthenticatedAs($customer, 'web');
    }

    public function test_a_signed_in_customer_checks_out_without_a_code(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($member, 'web');

        $this->checkout()->assertRedirect();
        $this->assertSame([], $this->whatsapp->sent);
        $this->assertSame($member->id, Order::withoutGlobalScopes()->sole()->customer_id);
    }

    public function test_a_disabled_account_cannot_be_used_at_checkout(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'password' => 'password123', 'is_active' => false]);

        $this->checkout();
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('checkout'))->assertSessionHasErrors('phone');
        $this->assertSame(0, Order::withoutGlobalScopes()->count());
        $this->assertGuest('web');
    }

    // ---- Sign in with a code -----------------------------------------------------

    public function test_an_account_signs_in_with_a_code(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'password' => 'password123', 'is_active' => true]);

        $this->onStore()->get('/login/otp')->assertOk()->assertSee('Sign in with WhatsApp');
        $this->onStore()->post('/login/otp', ['phone' => '98765 43210'])->assertRedirect(route('otp.verify'));
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('account.index'));

        $this->assertAuthenticatedAs($member, 'web');
        $this->assertNotNull($member->fresh()->phone_verified_at);
    }

    public function test_no_code_is_sent_for_an_unknown_number_or_a_guest_record(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Guest', 'email' => 'g@example.test', 'phone' => '9123456780', 'is_active' => true]);

        $this->onStore()->post('/login/otp', ['phone' => '9876543210'])->assertSessionHasErrors('phone');
        $this->onStore()->post('/login/otp', ['phone' => '9123456780'])->assertSessionHasErrors('phone');
        $this->assertSame([], $this->whatsapp->sent);
    }

    // ---- Forgot password: a WhatsApp code, then a new password ----------------------

    public function test_a_forgotten_password_is_reset_with_a_whatsapp_code(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'password' => 'old-pass-1', 'is_active' => true]);

        $this->onStore()->get('/forgot-password')->assertOk()->assertSee('name="phone"', false)->assertDontSee('name="email"', false);
        $this->onStore()->post('/forgot-password', ['phone' => '98765 43210'])->assertRedirect(route('otp.verify'));
        $this->onStore()->get('/verify')->assertOk()->assertSee('Reset your password');

        // Without the code the new-password page is closed.
        $this->onStore()->get('/new-password')->assertRedirect(route('password.request'));

        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('password.new'));
        $this->onStore()->get('/new-password')->assertOk()->assertSee('data-password-checklist', false)
            ->assertSee('Time remaining')
            ->assertSee('10:00');

        // The shared password rule applies.
        $this->onStore()->post('/new-password', ['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])->assertSessionHasErrors('password');

        // Saved, signed straight in, and the permission is used up.
        $this->onStore()->post('/new-password', ['password' => 'new-pass-2', 'password_confirmation' => 'new-pass-2'])
            ->assertRedirect(route('account.index'))
            ->assertSessionMissing('password_reset.allowed');
        $this->assertTrue(Hash::check('new-pass-2', $member->fresh()->password));
        $this->assertAuthenticatedAs($member, 'web');
    }

    public function test_the_new_password_page_closes_after_ten_minutes_and_says_why(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'password' => 'old-pass-1', 'is_active' => true]);
        $this->onStore()->post('/forgot-password', ['phone' => '9876543210']);
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('password.new'));

        $this->travel(11)->minutes();

        $this->onStore()->post('/new-password', ['password' => 'new-pass-2', 'password_confirmation' => 'new-pass-2'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['phone' => 'The 10 minutes to choose a new password have passed. Please verify your number again.']);
        $this->assertTrue(Hash::check('old-pass-1', $member->fresh()->password));
    }

    public function test_no_reset_code_is_sent_for_an_unknown_disabled_or_guest_number(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Guest', 'email' => 'g@example.test', 'phone' => '9123456780', 'is_active' => true]);
        Customer::create(['client_id' => $this->client->id, 'name' => 'Off', 'phone' => '9000000001', 'password' => 'pass-word-1', 'is_active' => false]);

        foreach (['9876543210', '9123456780', '9000000001'] as $phone) {
            $this->onStore()->post('/forgot-password', ['phone' => $phone])->assertSessionHasErrors('phone');
        }
        $this->assertSame([], $this->whatsapp->sent);
    }

    public function test_a_reset_code_from_one_store_does_not_open_another_stores_new_password_page(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'password' => 'old-pass-1', 'is_active' => true]);

        $this->onStore()->post('/forgot-password', ['phone' => '9876543210']);
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('password.new'));

        $this->onStore('beta')->get('/new-password')->assertRedirect(route('password.request'));
        $this->onStore('beta')->post('/new-password', ['password' => 'new-pass-2', 'password_confirmation' => 'new-pass-2'])
            ->assertRedirect(route('password.request'));
    }

    public function test_an_account_made_at_checkout_sets_its_first_password_without_a_current_one(): void
    {
        $this->checkout();
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha']);
        $customer = Customer::sole();
        $this->assertNull($customer->password);

        $this->onStore()->get('/account/profile')->assertOk()->assertDontSee('name="current_password"', false);
        $this->onStore()->put('/account/password', ['password' => 'N3w-pass-word', 'password_confirmation' => 'N3w-pass-word'])->assertSessionHasNoErrors();
        $this->assertSame('N3w-pass-word', $customer->fresh()->password_hint);

        auth('web')->logout();
        $this->onStore()->post('/login', ['phone' => '9876543210', 'password' => 'N3w-pass-word'])->assertRedirect(route('account.index'));
    }

    public function test_changing_the_password_from_the_account_page_updates_the_hint(): void
    {
        $member = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($member, 'web');

        $this->onStore()->put('/account/password', ['current_password' => 'password123', 'password' => 'N3w-pass-word', 'password_confirmation' => 'N3w-pass-word'])
            ->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame('N3w-pass-word', $member->password_hint);
        $this->assertTrue(Hash::check('N3w-pass-word', $member->password));
    }

    public function test_a_number_with_a_code_proven_account_cannot_be_registered_again(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'is_active' => true])
            ->forceFill(['phone_verified_at' => now()])->save();

        $this->register()->assertSessionHasErrors('phone');
        $this->assertSame([], $this->whatsapp->sent);
    }

    // ---- Every template ----------------------------------------------------------------

    public static function templates(): array
    {
        return array_map(fn ($t) => [$t], ['fashion' => 'fashion', 'grocery' => 'grocery', 'heritage' => 'heritage', 'plumbing' => 'plumbing', 'plumbing-services' => 'plumbing-services', 'bulk' => 'bulk']);
    }

    #[DataProvider('templates')]
    public function test_the_code_pages_render_in_every_template(string $template): void
    {
        $store = Storefront::firstOrCreate(['template' => $template], ['name' => "Store {$template}", 'slug' => "store-{$template}", 'is_active' => true]);
        $this->client->update(['storefront_id' => $store->id]);
        $url = fn (string $path) => $this->client->fresh()->publicUrl().$path;

        $this->get($url('/login'))->assertOk()->assertSee('/login/otp"', false);
        $this->get($url('/login/otp'))->assertOk()->assertSee('name="phone"', false)->assertSee('/login/otp"', false);

        $this->register()->assertRedirect(route('otp.verify'));
        $this->get($url('/verify'))->assertOk()->assertSee('name="code"', false)->assertSee('one-time-code')->assertSee('/verify/resend"', false);
    }

    // ---- The gateway ---------------------------------------------------------------

    public function test_the_gateway_is_called_with_the_configured_template(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.url' => 'https://wa.example.test/api/sendmsgutil.php',
            'services.whatsapp.user' => 'u', 'services.whatsapp.password' => 'p', 'services.whatsapp.sender' => 'BUZWAP',
            'services.whatsapp.country_code' => '',
        ]);
        Http::fake(['wa.example.test/*' => Http::response('S.12345', 200)]);

        $this->assertTrue((new WhatsApp)->sendTemplate('9876543210', 'otp', ['482913']));

        Http::assertSent(function (HttpRequest $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $q);

            return $q['user'] === 'u' && $q['pass'] === 'p' && $q['sender'] === 'BUZWAP' && $q['phone'] === '9876543210'
                && $q['text'] === 'visionotp' && $q['Params'] === '482913' && $q['priority'] === 'wa' && $q['stype'] === 'auth';
        });
    }

    public function test_a_gateway_error_is_reported_as_not_sent(): void
    {
        config(['services.whatsapp.enabled' => true, 'services.whatsapp.url' => 'https://wa.example.test/api/sendmsgutil.php']);
        Http::fake(['wa.example.test/*' => Http::response('Error: Invalid credentials', 200)]);

        $this->assertFalse((new WhatsApp)->sendTemplate('9876543210', 'otp', ['482913']));
    }

    // ---- One customer per phone: an earlier guest record becomes the account -----------------

    public function test_signing_up_with_a_guests_number_turns_that_guest_into_the_account(): void
    {
        // From an earlier checkout, saved in another shape, with its own email.
        $guest = Customer::create(['client_id' => $this->client->id, 'name' => 'A Rao', 'email' => 'old@example.test', 'phone' => '+91 98765 43210', 'is_active' => true]);

        $this->register(['phone' => '9876543210'])->assertSessionHasNoErrors()->assertRedirect(route('otp.verify'));
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('account.index'));

        $customer = Customer::sole();
        $this->assertSame($guest->id, $customer->id, 'No second customer: the guest record is the account.');
        $this->assertAuthenticatedAs($customer, 'web');
        $this->assertSame('Asha Rao', $customer->name);
        $this->assertSame('9876543210', $customer->phone);
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertTrue(Hash::check('Str0ng-pass-123', $customer->password));
        $this->assertSame('old@example.test', $customer->email, 'An email already on the record is kept.');
    }

    public function test_a_code_checkout_with_a_guests_number_keeps_the_order_on_that_guest(): void
    {
        // A phone-only customer, e.g. added by a sales person.
        $guest = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => null, 'phone' => '9876543210', 'is_active' => true]);

        $this->checkout()->assertRedirect(route('otp.verify'));
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha']);

        $this->assertSame(1, Customer::count());
        $this->assertSame($guest->id, Order::withoutGlobalScopes()->sole()->customer_id);
        $this->assertSame('asha@example.test', $guest->fresh()->email, 'The email typed at checkout fills an empty one.');
        $this->assertNotNull($guest->fresh()->phone_verified_at);
    }

    public function test_a_disabled_guest_record_is_not_signed_in_when_claimed(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '9876543210', 'is_active' => false]);

        $this->register(['email' => null]);
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('login'))->assertSessionHasErrors('phone');

        $this->assertGuest('web');
        $this->assertSame(1, Customer::count());
        $this->assertFalse(Customer::sole()->is_active, 'A record the store disabled stays disabled.');
    }

    public function test_an_account_is_found_by_its_number_in_any_saved_shape(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'phone' => '+91 98765 43210', 'password' => 'Str0ng-pass-123', 'is_active' => true]);

        $this->register(['phone' => '9876543210', 'email' => null])->assertSessionHasErrors(['phone' => 'An account with this phone number already exists. Try signing in instead.']);
        $this->assertSame(1, Customer::count());
        $this->assertCount(0, $this->whatsapp->sent);

        // And a code sign-in finds it the same way.
        $this->onStore()->post('/login/otp', ['phone' => '9876543210'])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->whatsapp->sent);
    }
}
