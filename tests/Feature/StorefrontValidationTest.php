<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Client;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Storefront forms are validated on the server only: the pages carry no browser validation
 * (forms are `novalidate`, no `required` attributes), required fields are marked with an
 * asterisk, and every rule is enforced by the form requests.
 */
class StorefrontValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function onStore(): static
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha']);
    }

    public function test_forms_have_no_browser_validation_and_mark_required_fields(): void
    {
        $html = $this->onStore()->get('/register')->assertOk()->getContent();

        $this->assertStringContainsString('novalidate', $html);
        $this->assertStringContainsString('class="req"', $html);
        $this->assertDoesNotMatchRegularExpression('/<(input|textarea|select)\b[^>]*\srequired\b/', $html);
    }

    public function test_an_empty_registration_is_rejected_field_by_field(): void
    {
        $this->onStore()->post('/register', [])
            ->assertSessionHasErrors(['name', 'phone', 'password'])->assertSessionDoesntHaveErrors('email');
        $this->assertSame(0, Customer::withoutGlobalScopes()->count());
    }

    public function test_registration_checks_the_format_of_each_field(): void
    {
        $this->onStore()->post('/register', [
            'name' => 'R2D2',
            'email' => 'not-an-email',
            'phone' => '12345',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'phone', 'password']);
    }

    public function test_only_a_mobile_number_is_accepted_when_one_is_entered(): void
    {
        // Landlines with their area code (Amritsar 0183, Delhi 011) and numbers starting 1 to 5.
        foreach (['0183 250 0000', '011 2345 6789', '5123456789', '1234567890'] as $landline) {
            $this->onStore()->post('/register', [
                'name' => 'Ravi', 'phone' => $landline, 'password' => 'password123', 'password_confirmation' => 'password123',
            ])->assertSessionHasErrors(['phone' => 'Please enter a 10-digit mobile number starting with 6, 7, 8 or 9, e.g. 9876543210.']);
        }
        $this->assertSame(0, Customer::withoutGlobalScopes()->count());

        foreach (['6012345678', '7012345678', '8012345678'] as $mobile) {
            $this->onStore()->post('/register', [
                'name' => 'Ravi', 'phone' => $mobile, 'password' => 'password123', 'password_confirmation' => 'password123',
            ])->assertSessionDoesntHaveErrors('phone');
        }
    }

    public function test_signing_in_still_takes_an_older_number_that_is_not_a_mobile(): void
    {
        // Saved before numbers were limited to mobiles: the account must still be reachable.
        $customer = Customer::withoutGlobalScopes()->create([
            'client_id' => Client::where('subdomain', 'alpha')->value('id'), 'name' => 'Ravi', 'phone' => '1201201201',
            'password' => 'password123', 'is_active' => true,
        ]);

        $this->onStore()->post('/login', ['phone' => '1201201201', 'password' => 'password123'])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($customer, 'web');
    }

    public function test_a_valid_registration_goes_through(): void
    {
        $this->onStore()->post('/register', [
            'name' => "Ravi O'Neil-Kumar",
            'email' => 'Ravi@Example.test',
            'phone' => '+91 98765 43210',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('otp.verify'));
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('account.index'));

        $this->assertSame('ravi@example.test', Customer::withoutGlobalScopes()->first()->email);
    }

    public function test_addresses_in_india_need_a_six_digit_pin_code(): void
    {
        $customer = Customer::withoutGlobalScopes()->create(['client_id' => Client::where('subdomain', 'alpha')->value('id'), 'name' => 'Ravi', 'email' => 'ravi@example.test', 'password' => 'password123', 'is_active' => true]);
        $address = ['name' => 'Ravi', 'line1' => '12 MG Road', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'country' => 'India'];

        $this->onStore()->actingAs($customer)->post('/account/addresses', $address + ['postal_code' => '5600'])
            ->assertSessionHasErrors(['postal_code' => 'Please enter a valid 6-digit PIN code.']);

        $this->onStore()->actingAs($customer)->post('/account/addresses', $address + ['postal_code' => '560001'])->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_contact_and_tracking_forms_validate_on_the_server(): void
    {
        $this->onStore()->post('/contact', [])->assertSessionHasErrors(['name', 'email', 'message']);
        $this->onStore()->get('/track-order?number=&email=')->assertRedirect(route('track'))->assertSessionHasErrors(['number', 'contact']);
    }

    public function test_json_forms_return_field_errors(): void
    {
        $this->onStore()->postJson('/newsletter', ['email' => 'nope'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->onStore()->postJson('/cart/coupon', ['code' => ''])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->onStore()->postJson('/delivery-pincode', ['pincode' => '12'])->assertStatus(422)->assertJsonValidationErrors('pincode');
        $this->onStore()->postJson('/delivery-pincode', ['pincode' => '560001'])->assertOk()->assertJson(['pincode' => '560001']);
    }

    public function test_checkout_rejects_missing_fields(): void
    {
        $this->onStore()->post('/checkout', [])
            ->assertSessionHasErrors(['phone', 'shipping_name', 'shipping_line1', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country', 'payment_method']);
    }

    public function test_addresses_must_be_in_india(): void
    {
        $customer = Customer::withoutGlobalScopes()->create(['client_id' => Client::where('subdomain', 'alpha')->value('id'), 'name' => 'Ravi', 'email' => 'ravi@example.test', 'password' => 'password123', 'is_active' => true]);
        $address = ['name' => 'Ravi', 'line1' => '12 MG Road', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560001', 'country' => 'India'];
        $post = fn (array $change) => $this->onStore()->actingAs($customer, 'web')->post('/account/addresses', $change + $address);

        $post(['country' => 'Canada', 'state' => 'Ontario', 'postal_code' => 'K1A 0B1'])->assertSessionHasErrors([
            'country' => 'We deliver within India only. Please enter India as the country.',
            'state' => 'Please enter a state or union territory of India, e.g. Maharashtra.',
            'postal_code' => 'Please enter a valid 6-digit PIN code.',
        ]);
        $post(['state' => 'Somewhere'])->assertSessionHasErrors(['state']);

        // Short forms are read and stored in full.
        $post(['country' => 'IN', 'state' => 'ka'])->assertSessionHasNoErrors()->assertRedirect();
        $saved = Address::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame(['Karnataka', 'India'], [$saved->state, $saved->country]);
    }

    public function test_checkout_delivers_within_india_only(): void
    {
        $this->onStore()->post('/checkout', [
            'shipping_state' => 'Ontario', 'shipping_postal_code' => 'K1A 0B1', 'shipping_country' => 'Canada',
        ])->assertSessionHasErrors([
            'shipping_country' => 'We deliver within India only. Please enter India as the country.',
            'shipping_state' => 'Please enter a state or union territory of India, e.g. Maharashtra.',
            'shipping_postal_code' => 'Please enter a valid 6-digit PIN code.',
        ]);

        $this->onStore()->post('/checkout', ['shipping_state' => 'MH', 'shipping_postal_code' => '400001', 'shipping_country' => 'Bharat'])
            ->assertSessionDoesntHaveErrors(['shipping_state', 'shipping_postal_code', 'shipping_country']);
    }
}
