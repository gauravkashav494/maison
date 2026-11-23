<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Menus\MenuResource;
use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\RelationManagers\ItemsRelationManager;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Storefront;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        // "Footer — Legal links": the picker's options travel as escaped JSON for its script,
        // so the dash is not plain text in the page.
        $this->assertStringContainsString('Legal links', $html);
        // The header already has the "Main menu".
        $this->assertStringNotContainsString('Header (primary navigation)', $html);
        foreach (['Indian Grocery', 'Heritage', 'Plumbing', 'Header quick links'] as $other) {
            $this->assertStringNotContainsString($other, $html, "a fashion store should not be offered {$other}");
        }
    }

    public function test_each_menu_says_where_it_shows_on_the_store_and_links_to_it(): void
    {
        $this->assertSame('At the top of every page', MenuResource::placement('header'));
        $this->assertSame('In the footer of every page, as its own column', MenuResource::placement('bulk_footer_shop'));
        $this->assertSame('On the bottom line of every page, under the footer', MenuResource::placement('grocery_legal'));
        $this->assertNull(MenuResource::placement('nowhere'));

        $this->actingAs($this->admin, 'client')->get('/client/menus')->assertOk()
            ->assertSee('Shows on the store')
            ->assertSee('At the top of every page');

        $menu = Menu::where('location', 'header')->firstOrFail();
        $this->get('/client/menus/'.$menu->getKey().'/edit')->assertOk()
            ->assertSee('At the top of every page')
            ->assertSee('View on storefront')
            ->assertSee('Where it shows')
            // The store itself, opened with this menu's area outlined.
            ->assertSee('src="'.e(rtrim($this->client->publicUrl(), '/').'/?menu_preview=header').'"', false)
            // "View on storefront" opens the whole page, scrolled to the menu.
            ->assertSee('menu_preview=header&amp;menu_preview_page=1', false);
    }

    public function test_where_it_shows_comes_after_the_menu_items_and_reloads_when_they_change(): void
    {
        $menu = Menu::where('location', 'header')->firstOrFail();
        $html = $this->actingAs($this->admin, 'client')->get('/client/menus/'.$menu->getKey().'/edit')->assertOk()->getContent();

        // The items section loads after the page, so its place is marked by its component.
        $items = strpos($html, 'RelationManagers\\\\ItemsRelationManager');
        $preview = strpos($html, 'Where it shows');
        $this->assertNotFalse($items);
        $this->assertNotFalse($preview);
        $this->assertGreaterThan($items, $preview, 'the store preview comes after the menu items');
        $this->assertSame(1, substr_count($html, 'Where it shows'), 'shown once, not also inside the form');

        // A new menu has no items yet: the preview sits in the form and follows the location picked.
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($this->admin);
        app(StoreContext::class)->resolve($this->admin, app(ClientContext::class)->templateId());
        Livewire::test(CreateMenu::class)
            ->assertDontSee('Where it shows')
            ->fillForm(['location' => 'footer_shop'])
            ->assertSee('Where it shows')
            ->assertSee('menu_preview=footer_shop', false);

        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($this->admin);
        app(StoreContext::class)->resolve($this->admin, app(ClientContext::class)->templateId());
        Livewire::test(ItemsRelationManager::class, [
            'ownerRecord' => $menu, 'pageClass' => EditMenu::class,
        ])
            ->callAction(TestAction::make('create')->table(), ['label' => 'Brands', 'url' => '/brands'])
            ->assertHasNoFormErrors()
            ->assertDispatched('menu-items-changed');
    }

    public function test_the_item_form_offers_every_field_for_every_menu(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('client'));
        $this->actingAs($this->admin, 'client');
        app(ClientContext::class)->resolveForIdentity($this->admin);
        app(StoreContext::class)->resolve($this->admin, app(ClientContext::class)->templateId());

        $footer = Menu::create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Legal', 'location' => 'legal']);
        foreach ([Menu::where('location', 'header')->firstOrFail(), $footer] as $menu) {
            $test = Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $menu, 'pageClass' => EditMenu::class])
                ->mountAction(TestAction::make('create')->table());
            foreach (['label', 'url', 'is_active', 'opens_in_new_tab', 'is_accent', 'parent_id', 'group', 'badge', 'eyebrow', 'image'] as $field) {
                $test->assertFormFieldVisible($field);
            }
            $this->assertTrue($test->instance()->getMountedAction()->isModalSlideOver(), 'items open in a side panel');
        }
    }

    public function test_the_store_outlines_the_previewed_menu_area_and_ignores_anything_else(): void
    {
        $store = $this->withSession([Client::SESSION_KEY => 'alpha']);

        $store->get('/shop?menu_preview=footer_shop')->assertOk()
            ->assertSee('data-menu-area="footer_shop"', false)
            ->assertSee('data-menu-preview-hit', false)
            ->assertSee('<meta name="robots" content="noindex">', false);

        // Only a plain area name switches it on; nothing from the address is echoed into the script.
        // The whole page: nothing hidden, the menu outlined, and a note saying so.
        $page = $store->get('/shop?menu_preview=footer_shop&menu_preview_page=1')->assertOk();
        $page->assertSee('This menu is outlined in orange.')->assertDontSee('overflow: hidden !important', false);
        $store->get('/shop?menu_preview=footer_shop')->assertSee('overflow: hidden !important', false);

        $store->get('/shop?menu_preview=%3Cscript%3E')->assertOk()->assertDontSee('data-menu-preview-hit', false);
        $store->get('/shop')->assertOk()->assertDontSee('data-menu-preview-hit', false);
    }

    public function test_placement_names_the_exceptions_of_each_store_design(): void
    {
        $this->assertSame('In the footer of every page, as the Policies column', MenuResource::placement('plumbing_legal'));
        $this->assertStringContainsString('first 4 links', MenuResource::placement('grocery_header'));
        $this->assertSame('In the footer of every page, as its own column', MenuResource::placement('heritage_footer_shop'));
        $this->assertSame('footer_shop', MenuResource::alias('bulk_footer_shop'));
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
        $html = $this->actingAs($this->admin, 'client')->get('/client/menus/create')->assertOk()->getContent();
        $this->assertStringContainsString('Legal links', $html); // "Footer — Legal links"
    }

    public function test_editing_a_menu_keeps_its_own_location_in_the_list(): void
    {
        $this->fillEveryLocation();
        $header = Menu::where('location', 'header')->firstOrFail();

        $this->actingAs($this->admin, 'client')->get('/client/menus/'.$header->id.'/edit')->assertOk()
            ->assertSee('Header (primary navigation)')
            ->assertDontSee('Legal links'); // "Footer — Legal links", escaped in the picker's JSON
    }
}
