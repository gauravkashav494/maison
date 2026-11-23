<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * After 2 hours without use the visitor is signed out and taken to sign in (partials/session-timeout),
 * in both panels and every storefront template, with no alert; a form sent after the session ended
 * comes back with what was typed, instead of Laravel's "Page expired" page.
 */
class SessionTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function store(string $template): Client
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => ucfirst($template).' Store', 'slug' => $template, 'template' => $template, 'is_active' => true]);

        return Client::create(['name' => ucfirst($template), 'subdomain' => 'shop-'.$template, 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    public static function templates(): array
    {
        return [['fashion'], ['grocery'], ['heritage'], ['plumbing'], ['plumbing-services'], ['bulk']];
    }

    /** @dataProvider templates */
    public function test_a_signed_in_customer_gets_the_idle_clock_on_every_template(string $template): void
    {
        $client = $this->store($template);
        $customer = Customer::create(['client_id' => $client->id, 'name' => 'Asha Rao', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);

        $this->actingAs($customer, 'web')->get($client->publicUrl().'/')->assertOk()
            ->assertSee('id="session-timeout"', false)
            ->assertSee('data-minutes="'.config('session.lifetime').'"', false)
            ->assertSee('data-signed-in="1"', false)
            ->assertSee('data-logout-url="'.route('logout').'"', false);
    }

    public function test_a_guest_gets_only_the_expired_reload_not_the_idle_clock(): void
    {
        $client = $this->store('fashion');

        $this->get($client->publicUrl().'/')->assertOk()
            ->assertSee('id="session-timeout"', false)
            ->assertSee('data-signed-in="0"', false)
            ->assertSee('data-logout-url=""', false);
    }

    public function test_the_client_panel_runs_the_idle_clock_for_signed_in_staff(): void
    {
        $client = $this->store('fashion');
        $admin = ClientUser::create([
            'client_id' => $client->id, 'name' => 'Priya Sharma', 'email' => 'priya@example.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        $this->actingAs($admin, 'client')->get('/client')->assertOk()
            ->assertSee('data-signed-in="1"', false)
            ->assertSee('/client/logout', false);
    }

    public function test_a_form_sent_after_the_session_ended_goes_back_with_its_input(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => throw new TokenMismatchException);
        $shop = $this->store('fashion')->publicUrl();

        $this->from($shop.'/contact')
            ->post($shop.'/_test/expired', ['name' => 'Asha', 'password' => 'secret123'])
            ->assertRedirect($shop.'/contact')
            ->assertSessionHasInput('name', 'Asha')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_a_background_request_after_the_session_ended_gets_419_with_a_message(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => throw new TokenMismatchException);

        $this->postJson($this->store('fashion')->publicUrl().'/_test/expired')->assertStatus(419)->assertJson(['session_expired' => true]);
    }

    /**
     * The idle sign-out is sent from a page whose session may have expired already. If its old
     * form token were refused, a "remember me" visitor would be signed straight back in.
     */
    public function test_signing_out_does_not_need_a_valid_form_token(): void
    {
        $excluded = app(ValidateCsrfToken::class)->getExcludedPaths();

        $this->assertContains('logout', $excluded);        // storefront and Super Admin panel
        $this->assertContains('client/logout', $excluded); // client panel
        $this->assertNotContains('cart/items', $excluded);
    }

    public function test_signing_out_with_an_expired_page_just_goes_on(): void
    {
        Route::middleware('web')->post('/_test/logout', fn () => throw new TokenMismatchException);

        $shop = $this->store('fashion')->publicUrl();

        $this->from($shop.'/account')->post($shop.'/_test/logout')
            ->assertRedirect($shop.'/account')
            ->assertSessionMissing('_old_input');
    }
}
