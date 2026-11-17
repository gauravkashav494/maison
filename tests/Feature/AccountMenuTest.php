<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A signed-in customer can log out from the header on every page, in every template, not only
 * from inside the account area. A guest still gets the plain sign-in link.
 */
class AccountMenuTest extends TestCase
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
    public function test_a_signed_in_customer_can_log_out_from_the_header(string $template): void
    {
        $client = $this->store($template);
        $customer = Customer::create(['client_id' => $client->id, 'name' => 'Asha Rao', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);

        $html = $this->actingAs($customer, 'web')->get($client->publicUrl().'/')->assertOk()->getContent();

        $header = substr($html, 0, strpos($html, '</header>'));
        $this->assertStringContainsString('Signed in as <span class="font-semibold">Asha Rao</span>', $header);
        $this->assertMatchesRegularExpression('#action="[^"]*/logout"#', $header);
        $this->assertStringContainsString('Log out', $header);
    }

    /** @dataProvider templates */
    public function test_a_guest_gets_the_sign_in_link(string $template): void
    {
        $client = $this->store($template);

        $html = $this->get($client->publicUrl().'/')->assertOk()->getContent();

        $header = substr($html, 0, strpos($html, '</header>'));
        $this->assertMatchesRegularExpression('#href="[^"]*/login"#', $header);
        $this->assertStringNotContainsString('Signed in as', $header);
    }

    public function test_logging_out_ends_the_session(): void
    {
        $client = $this->store('fashion');
        $customer = Customer::create(['client_id' => $client->id, 'name' => 'Asha Rao', 'email' => 'asha@example.test', 'password' => 'password123', 'is_active' => true]);

        $this->actingAs($customer, 'web')->post($client->publicUrl().'/logout')->assertRedirect();

        $this->assertGuest('web');
    }
}
