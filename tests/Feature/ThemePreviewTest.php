<?php

namespace Tests\Feature;

use App\Clients\ClientDatabase;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Theme previews: a public sample store per template at theme-<template>.<base domain>, with
 * its own sample content, that can be browsed but not ordered from, and that no client list shows.
 */
class ThemePreviewTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    protected function setUp(): void
    {
        parent::setUp();

        config(['clients.isolation' => 'database']);

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha-theme-test', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        if ($theme = Client::themeFor('fashion')) {
            DB::purge(app(ClientDatabase::class)->connectionNameFor($theme));
            File::delete(database_path($theme->databaseName().'.sqlite'));
        }

        parent::tearDown();
    }

    private function theme(): Client
    {
        $this->artisan('themes:build', ['template' => 'fashion'])->assertSuccessful();

        return Client::themeFor('fashion');
    }

    public function test_a_theme_is_public_with_sample_content_and_kept_out_of_search(): void
    {
        $theme = $this->theme();

        $this->assertSame('https://theme-fashion.'.Client::baseDomain(), $theme->publicUrl());

        $this->get($theme->publicUrl().'/')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Theme preview of the', false);
        $this->get($theme->publicUrl().'/shop')->assertOk();

        $samples = app(ClientDatabase::class)->forClient($theme, fn () => \App\Models\Product::withoutGlobalScopes()->count());
        $this->assertGreaterThan(0, $samples, 'the theme has no sample products');
    }

    public function test_nothing_can_be_ordered_or_signed_into_on_a_theme(): void
    {
        $theme = $this->theme();

        foreach (['/checkout', '/login', '/register', '/contact', '/newsletter'] as $path) {
            $this->post($theme->publicUrl().$path, ['email' => 'someone@example.test', 'password' => 'password123'])->assertRedirect();
        }
        $this->assertGuest('web');
        $this->postJson($theme->publicUrl().'/checkout', [])->assertForbidden();

        // The cart still works, so the layout can be tried end to end.
        $this->assertNotSame(403, $this->postJson($theme->publicUrl().'/cart/items', [])->getStatusCode());
    }

    public function test_a_theme_is_in_no_client_list(): void
    {
        $theme = $this->theme();

        $this->assertSame(1, Client::count());
        $this->assertSame([$this->alpha->id], Client::pluck('id')->all());
        $this->assertTrue(Client::withThemes()->whereKey($theme->id)->exists());

        $this->actingAs(User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'secret-pass', 'role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]), 'admin');
        $this->get('/clients')->assertOk()->assertSee('Alpha')->assertDontSee('theme-fashion');
    }

    public function test_store_links_open_the_theme(): void
    {
        $base = Client::baseDomain();

        // Before the theme is set up there is nothing to show.
        $this->get("https://fashion.{$base}/")->assertNotFound();

        $theme = $this->theme();

        $this->get("https://fashion.{$base}/shop?page=2")->assertRedirect($theme->publicUrl().'/shop?page=2');
        $this->get('/store/fashion')->assertRedirect($theme->publicUrl().'/');
    }

    public function test_client_names_cannot_take_the_theme_prefix(): void
    {
        $this->assertSame('store-theme-fashion', Client::uniqueSubdomain('Theme Fashion'));
    }
}
