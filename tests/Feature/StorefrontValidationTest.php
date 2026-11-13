<?php

namespace Tests\Feature;

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
            ->assertSessionHasErrors(['name', 'email', 'password']);
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

    public function test_a_valid_registration_goes_through(): void
    {
        $this->onStore()->post('/register', [
            'name' => "Ravi O'Neil-Kumar",
            'email' => 'Ravi@Example.test',
            'phone' => '+91 98765 43210',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('account.index'));

        $this->assertSame('ravi@example.test', Customer::withoutGlobalScopes()->first()->email);
    }

    public function test_addresses_in_india_need_a_six_digit_pin_code(): void
    {
        $customer = Customer::withoutGlobalScopes()->create(['client_id' => Client::where('subdomain', 'alpha')->value('id'), 'name' => 'Ravi', 'email' => 'ravi@example.test', 'password' => 'password123']);
        $address = ['name' => 'Ravi', 'line1' => '12 MG Road', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'country' => 'India'];

        $this->onStore()->actingAs($customer)->post('/account/addresses', $address + ['postal_code' => '5600'])
            ->assertSessionHasErrors(['postal_code' => 'Please enter a valid 6-digit PIN code.']);

        $this->onStore()->actingAs($customer)->post('/account/addresses', $address + ['postal_code' => '560001'])
            ->assertSessionHasNoErrors();
    }

    public function test_contact_and_tracking_forms_validate_on_the_server(): void
    {
        $this->onStore()->post('/contact', [])->assertSessionHasErrors(['name', 'email', 'message']);
        $this->onStore()->get('/track-order?number=&email=')->assertRedirect(route('track'))->assertSessionHasErrors(['number', 'email']);
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
            ->assertSessionHasErrors(['email', 'phone', 'shipping_name', 'shipping_line1', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country', 'payment_method']);
    }
}
