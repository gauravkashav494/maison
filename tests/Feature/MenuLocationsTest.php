<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Navigation (menus) screens offer a store only its own template's menu locations, not
 * every template's: a fashion store never sees "Indian Grocery — Header quick links".
 */
class MenuLocationsTest extends TestCase
{
    use RefreshDatabase;

    private ClientUser $admin;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $client = $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->admin = ClientUser::create([
            'client_id' => $client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        Menu::create(['client_id' => $client->id, 'template' => 'fashion', 'name' => 'Main menu', 'location' => 'header']);
    }

    public function test_the_location_picker_offers_only_the_stores_own_free_locations(): void
    {
        $html = $this->actingAs($this->admin, 'client')->get('/client/menus/create')->assertOk()->getContent();

        $this->assertStringContainsString('Footer — Legal links', $html);
        // The header already has the "Main menu".
        $this->assertStringNotContainsString('Header (primary navigation)', $html);
        foreach (['Indian Grocery', 'Heritage', 'Plumbing', 'Header quick links'] as $other) {
            $this->assertStringNotContainsString($other, $html, "a fashion store should not be offered {$other}");
        }
    }

    public function test_the_list_shows_plain_location_names_and_no_template_column(): void
    {
        $html = $this->actingAs($this->admin, 'client')->get('/client/menus')->assertOk()->getContent();

        $this->assertStringContainsString('Main menu', $html);
        $this->assertStringContainsString('Header (primary navigation)', $html);
        $this->assertStringNotContainsString('Fashion Store — Header', $html);
        $this->assertStringNotContainsString('Indian Grocery', $html);
    }

    /** Gives every remaining Fashion location a menu, so none is free. */
    private function fillEveryLocation(): void
    {
        foreach (['footer_shop', 'footer_collections', 'footer_about', 'footer_service', 'legal'] as $location) {
            Menu::create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => $location, 'location' => $location]);
        }
    }

    public function test_new_menu_shows_only_while_a_location_is_free(): void
    {
        $this->actingAs($this->admin, 'client')->get('/client/menus')->assertOk()
            ->assertSee('/client/menus/create', false)
            ->assertDontSee('Every menu location on your storefront already has a menu');

        $this->fillEveryLocation();

        $this->actingAs($this->admin, 'client')->get('/client/menus')->assertOk()
            ->assertDontSee('/client/menus/create', false)
            ->assertSee('Every menu location on your storefront already has a menu');
    }

    public function test_opening_create_with_no_free_location_goes_back_to_the_list(): void
    {
        $this->fillEveryLocation();

        $this->actingAs($this->admin, 'client')->get('/client/menus/create')->assertRedirect('/client/menus');
    }

    public function test_a_deleted_menu_keeps_its_location_until_deleted_permanently(): void
    {
        $this->fillEveryLocation();
        $legal = Menu::where('location', 'legal')->firstOrFail();

        $legal->delete();
        $this->actingAs($this->admin, 'client')->get('/client/menus/create')->assertRedirect('/client/menus');

        $legal->forceDelete();
        $this->actingAs($this->admin, 'client')->get('/client/menus/create')->assertOk()->assertSee('Footer — Legal links');
    }

    public function test_editing_a_menu_keeps_its_own_location_in_the_list(): void
    {
        $this->fillEveryLocation();
        $header = Menu::where('location', 'header')->firstOrFail();

        $this->actingAs($this->admin, 'client')->get('/client/menus/'.$header->id.'/edit')->assertOk()
            ->assertSee('Header (primary navigation)')
            ->assertDontSee('Footer — Legal links');
    }
}
