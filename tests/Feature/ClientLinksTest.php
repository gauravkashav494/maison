<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The store link and the client login are shown on the same address. */
class ClientLinksTest extends TestCase
{
    use RefreshDatabase;

    private function client(array $attributes = []): Client
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::firstOrCreate(['slug' => 'fashion'], ['name' => 'Fashion Store', 'template' => 'fashion', 'is_active' => true]);

        return Client::create($attributes + ['name' => 'Acme', 'subdomain' => 'acme', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    public function test_the_login_is_on_the_store_subdomain(): void
    {
        config(['templates.base_domain' => 'shop.test', 'app.url' => 'https://shop.test']);

        $client = $this->client();

        $this->assertSame('https://acme.shop.test', $client->publicUrl());
        $this->assertSame('https://acme.shop.test/client/login', $client->loginUrl());
    }

    public function test_a_custom_domain_is_used_for_both(): void
    {
        config(['templates.base_domain' => 'shop.test']);

        $client = $this->client(['domain' => 'store.acme.com']);

        $this->assertSame('https://store.acme.com/client/login', $client->loginUrl());
    }

    public function test_the_login_follows_the_client_subdomain(): void
    {
        config(['templates.base_domain' => 'shop.test', 'app.url' => 'https://shop.test']);

        $this->assertSame('https://acme.shop.test/client/login', $this->client()->loginUrl());
    }

    public function test_old_entry_links_redirect_to_the_subdomain(): void
    {
        config(['templates.base_domain' => 'shop.test']);
        $this->client();

        $this->get('/store/acme')->assertRedirect('https://acme.shop.test/')->assertStatus(301)->assertCookieExpired('client');
        $this->get('/c/acme')->assertRedirect('https://acme.shop.test/');
        $this->get('/store/acme?to=/shop')->assertRedirect('https://acme.shop.test/shop');
        $this->get('/store/acme?to=//evil.test')->assertRedirect('https://acme.shop.test/');
        $this->get('/c/nobody')->assertNotFound();
    }

    public function test_a_store_link_without_a_theme_is_not_found(): void
    {
        Storefront::create(['name' => 'Bulk', 'slug' => 'bulk', 'template' => 'bulk', 'is_active' => true]);

        // Store links open the theme preview (ThemePreviewTest); with none set up, nothing.
        $this->get('/store/bulk')->assertNotFound();
        $this->get('https://bulk.'.Storefront::baseDomain().'/')->assertNotFound();
    }

    public function test_the_super_admin_panel_is_not_on_a_client_subdomain(): void
    {
        $client = $this->client();

        $this->get($client->publicUrl().'/login')->assertOk()->assertDontSee('fi-login', false);
        $this->get($client->publicUrl().'/clients')->assertNotFound();
    }

    public function test_the_main_domain_ignores_an_old_client_cookie(): void
    {
        config(['templates.base_domain' => 'shop.test']);
        $this->client(['name' => 'Acme Unique Shop']);

        $this->withCookie('client', 'acme')->get('/')->assertRedirect('/login');
    }

    public function test_the_login_page_answers_on_the_client_subdomain(): void
    {
        config(['templates.base_domain' => 'shop.test', 'app.url' => 'https://shop.test']);

        $this->get($this->client()->loginUrl())->assertOk();
    }
}
