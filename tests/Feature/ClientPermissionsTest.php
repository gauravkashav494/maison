<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\ClientModules;
use App\Filament\Resources\ClientRoles\Pages\CreateClientRole;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PlatformActivity;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Storefront;
use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ClientPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Who sees what. A SuperAdmin runs the platform and sees totals; a client's module list and a
 * staff member's role decide everything else — and refusal is checked on the server, by asking
 * for the URL, rather than by what the menu happens to show.
 */
class ClientPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private Client $beta;

    private User $operator;

    private ClientUser $alphaAdmin;

    private Product $alphaProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);

        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->operator = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $this->alphaAdmin = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        $this->alphaProduct = Product::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion',
            'name' => 'Alpha Coat', 'slug' => 'alpha-coat', 'price' => 1000, 'is_active' => true,
        ]);
    }

    /** A staff account whose role holds exactly these permissions. */
    private function staff(array $permissions, ?Client $roleClient = null): ClientUser
    {
        $role = ClientRole::create([
            'client_id' => ($roleClient ?? $this->alpha)->id, 'name' => 'Test role '.uniqid(), 'permissions' => $permissions,
        ]);

        return ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Alpha Staff', 'email' => uniqid().'@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'client_role_id' => $role->id, 'is_active' => true,
        ]);
    }

    /** Livewire component tests skip the panel middleware, so the request context is set here. */
    private function enterClientPanel(ClientUser $user): void
    {
        $this->actingAs($user, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($user);
        app(StoreContext::class)->resolve($user, app(ClientContext::class)->templateId());
    }

    // ---- SuperAdmin -------------------------------------------------------------------------

    public function test_the_operator_reaches_the_platform_screens(): void
    {
        $this->actingAs($this->operator, 'admin');

        foreach (['', '/clients', '/client-users', '/client-totals', '/storefronts', '/plans', '/users', '/platform-activities', '/appearance/templates', '/platform-settings'] as $path) {
            $this->get($path === '' ? '/' : $path)->assertSuccessful();
        }
    }

    public function test_no_client_business_screen_exists_on_the_platform_panel(): void
    {
        $this->actingAs($this->operator, 'admin');

        foreach ([
            'orders', 'customers', 'products', 'categories', 'collections', 'reviews', 'coupons', 'pages', 'posts',
            'faqs', 'menus', 'contact-messages', 'subscribers', 'services', 'service-areas', 'service-requests',
            'projects', 'testimonials', 'stores', 'client-roles', 'homepage-settings', 'grocery-settings',
        ] as $path) {
            $this->assertNotSame(200, $this->get('/'.$path)->getStatusCode(), $path);
        }
    }

    public function test_the_platform_panel_registers_nothing_a_client_owns(): void
    {
        $clientOwned = array_filter(array_column(ClientModules::all(), 'model'));

        foreach (AdminPanelProvider::RESOURCES as $resource) {
            $this->assertNotContains($resource::getModel(), $clientOwned, $resource);
        }
    }

    public function test_the_operator_cannot_open_a_client_through_the_url(): void
    {
        $this->actingAs($this->operator, 'admin');

        $this->get('/?switch_client='.$this->alpha->id)->assertSuccessful();
        $this->assertNull(session(ClientContext::SESSION_KEY));
        $this->assertNull(app(ClientContext::class)->client());
    }

    public function test_the_operator_sees_totals_and_never_a_customer_or_order(): void
    {
        Customer::create(['client_id' => $this->alpha->id, 'name' => 'Asha Shopper', 'email' => 'asha@shopper.test', 'password' => 'password123', 'is_active' => true]);
        Order::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'number' => 'ME-12345', 'email' => 'asha@shopper.test',
            'shipping_name' => 'Asha Shopper', 'shipping_line1' => '1 Test Road', 'shipping_city' => 'Delhi',
            'shipping_state' => 'Delhi', 'shipping_postal_code' => '110001', 'shipping_country' => 'IN', 'phone' => '9999999999',
            'shipping_method' => 'standard', 'payment_method' => 'card',
            'status' => 'confirmed', 'payment_status' => 'paid', 'subtotal' => 700, 'total' => 700,
        ]);
        $this->actingAs($this->operator, 'admin');

        foreach (['/', '/client-totals'] as $path) {
            $this->get($path)->assertSuccessful()
                ->assertDontSee('asha@shopper.test')
                ->assertDontSee('Asha Shopper')
                ->assertDontSee('ME-12345')
                ->assertDontSee('Alpha Coat');
        }

        $this->get('/client-totals')->assertSee('Alpha')->assertSee(money(700));
    }

    public function test_the_operator_sees_client_admins_but_not_the_rest_of_their_team(): void
    {
        $staff = $this->staff(['products.view']);
        $this->actingAs($this->operator, 'admin');

        $this->get('/client-users')->assertSuccessful()
            ->assertSee('admin@alpha.test')
            ->assertDontSee($staff->email);
        $this->get('/client-users/'.$staff->id.'/edit')->assertNotFound();
    }

    public function test_a_client_account_never_reaches_the_platform_panel(): void
    {
        $this->actingAs($this->alphaAdmin, 'client');

        $this->get('/')->assertRedirect('/login');
        $this->get('/client-totals')->assertRedirect('/login');
    }

    // ---- Staff roles ------------------------------------------------------------------------

    public function test_staff_without_a_role_open_the_dashboard_and_nothing_else(): void
    {
        $staff = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'New Staff', 'email' => 'new@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'is_active' => true,
        ]);
        $this->actingAs($staff, 'client');

        $this->get('/client')->assertSuccessful();
        foreach (['products', 'orders', 'customers', 'pages', 'menus', 'coupons', 'client-users', 'client-roles'] as $path) {
            $this->get('/client/'.$path)->assertForbidden();
        }
    }

    public function test_a_role_grants_exactly_what_it_lists(): void
    {
        $this->actingAs($this->staff(['products.view']), 'client');

        $this->get('/client/products')->assertSuccessful()->assertSee('Alpha Coat');
        $this->get('/client/products/create')->assertForbidden();
        $this->get('/client/products/'.$this->alphaProduct->id.'/edit')->assertForbidden();
        $this->get('/client/orders')->assertForbidden();
        $this->get('/client/customers')->assertForbidden();
    }

    public function test_the_menu_only_offers_what_the_role_allows(): void
    {
        $this->actingAs($this->staff(['products.view']), 'client');

        $this->get('/client')->assertSuccessful()
            ->assertSee('/client/products')
            ->assertDontSee('/client/orders')
            ->assertDontSee('/client/client-users');
    }

    public function test_edit_permission_opens_the_form_without_granting_delete_or_create(): void
    {
        $staff = $this->staff(['products.view', 'products.update']);
        $this->actingAs($staff, 'client');

        $this->get('/client/products/'.$this->alphaProduct->id.'/edit')->assertSuccessful();
        $this->get('/client/products/create')->assertForbidden();
        $this->assertFalse($staff->can('delete', $this->alphaProduct));
    }

    public function test_a_role_of_another_client_grants_nothing(): void
    {
        $this->actingAs($this->staff(['products.view'], $this->beta), 'client');

        $this->get('/client/products')->assertForbidden();
    }

    public function test_only_a_client_admin_manages_staff_and_roles(): void
    {
        $this->actingAs($this->staff(ClientModules::permissions()), 'client');
        $this->get('/client/client-users')->assertForbidden();
        $this->get('/client/client-roles')->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->alphaAdmin, 'client');
        $this->get('/client/client-users')->assertSuccessful();
        $this->get('/client/client-roles')->assertSuccessful();
    }

    public function test_a_client_admin_cannot_open_another_clients_role(): void
    {
        $theirs = $this->beta->roles()->first();
        $this->actingAs($this->alphaAdmin, 'client');

        $this->get('/client/client-roles/'.$theirs->id.'/edit')->assertNotFound();
    }

    public function test_a_new_role_belongs_to_the_signed_in_client_and_keeps_only_real_permissions(): void
    {
        $this->enterClientPanel($this->alphaAdmin);

        Livewire::test(CreateClientRole::class)
            ->fillForm([
                'name' => 'Stock keeper',
                'client_id' => $this->beta->id,
                'grid' => ['products' => ['view', 'update'], 'orders' => ['view']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = ClientRole::where('name', 'Stock keeper')->firstOrFail();
        $this->assertSame($this->alpha->id, (int) $role->client_id);
        $this->assertEqualsCanonicalizing(['products.view', 'products.update', 'orders.view'], $role->permissions);
    }

    public function test_every_client_starts_with_the_ready_made_roles(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Manager', 'Catalogue', 'Customer support', 'Content editor'],
            $this->alpha->roles()->pluck('name')->all(),
        );
    }

    public function test_the_customer_list_filters_by_the_date_customers_joined(): void
    {
        $joined = function (string $name, $when) {
            $customer = Customer::create(['client_id' => $this->alpha->id, 'name' => $name, 'email' => strtolower($name).'@alpha.test', 'password' => 'password123', 'is_active' => true]);
            $customer->forceFill(['created_at' => $when])->save();

            return $customer;
        };
        $old = $joined('Old', now()->subDays(30));
        $mid = $joined('Mid', now()->subDays(10)->setTime(23, 30));
        $new = $joined('New', now());

        $this->enterClientPanel($this->staff(['customers.view']));
        $day = fn (int $ago) => now()->subDays($ago)->toDateString();

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$old, $mid, $new])
            // A range, inclusive at both ends (a late-evening sign-up still counts for its day).
            ->filterTable('joined', ['from' => $day(12), 'until' => $day(10)])
            ->assertCanSeeTableRecords([$mid])->assertCanNotSeeTableRecords([$old, $new])
            // One day: the same date at both ends.
            ->filterTable('joined', ['from' => $day(10), 'until' => $day(10)])
            ->assertCanSeeTableRecords([$mid])->assertCanNotSeeTableRecords([$old, $new])
            // Either end on its own.
            ->filterTable('joined', ['from' => $day(10), 'until' => null])
            ->assertCanSeeTableRecords([$mid, $new])->assertCanNotSeeTableRecords([$old])
            ->filterTable('joined', ['from' => null, 'until' => $day(10)])
            ->assertCanSeeTableRecords([$old, $mid])->assertCanNotSeeTableRecords([$new])
            ->resetTableFilters()
            ->assertCanSeeTableRecords([$old, $mid, $new]);
    }

    // ---- Writes that do not pass through a page ---------------------------------------------

    public function test_a_view_only_role_cannot_flip_a_table_toggle(): void
    {
        $this->enterClientPanel($this->staff(['products.view']));

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'is_active', (string) $this->alphaProduct->id, false);

        $this->assertTrue((bool) $this->alphaProduct->fresh()->is_active);
    }

    public function test_an_editing_role_can_flip_a_table_toggle(): void
    {
        $this->enterClientPanel($this->staff(['products.view', 'products.update']));

        Livewire::test(ListProducts::class)
            ->call('updateTableColumnState', 'is_active', (string) $this->alphaProduct->id, false);

        $this->assertFalse((bool) $this->alphaProduct->fresh()->is_active);
    }

    public function test_a_view_only_role_cannot_approve_a_review(): void
    {
        $review = Review::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'product_id' => $this->alphaProduct->id,
            'name' => 'A Reader', 'email' => 'reader@test.test', 'rating' => 5, 'title' => 'Good', 'body' => 'Very good', 'is_approved' => false,
        ]);
        $this->enterClientPanel($this->staff(['reviews.view']));

        Livewire::test(ListReviews::class)->assertTableActionHidden('approve', $review);

        $this->assertFalse((bool) $review->fresh()->is_approved);
    }

    // ---- Modules ----------------------------------------------------------------------------

    public function test_a_module_switched_off_is_gone_even_for_the_client_admin(): void
    {
        $this->alpha->update(['modules' => ['orders', 'customers']]);
        $this->actingAs($this->alphaAdmin->fresh(), 'client');

        $this->get('/client/products')->assertForbidden();
        $this->get('/client/products/'.$this->alphaProduct->id.'/edit')->assertForbidden();
        $this->get('/client/orders')->assertSuccessful();
        $this->get('/client')->assertSuccessful()->assertDontSee('/client/products');
    }

    public function test_a_module_switched_off_overrides_a_role_that_grants_it(): void
    {
        $staff = $this->staff(['products.view']);
        $this->alpha->update(['modules' => ['orders']]);
        $this->actingAs($staff->fresh(), 'client');

        $this->get('/client/products')->assertForbidden();
    }

    public function test_a_client_with_no_module_list_has_everything_its_store_supports(): void
    {
        $this->assertNull($this->alpha->modules);
        $this->actingAs($this->alphaAdmin, 'client');

        $this->get('/client/products')->assertSuccessful();
        $this->get('/client/services')->assertForbidden(); // a fashion store has no services
    }

    public function test_every_client_screen_is_covered_by_the_catalogue(): void
    {
        $accounts = ['ClientUser', 'ClientRole']; // managed by Client Admins only, by their own policies

        foreach (ClientPanelProvider::RESOURCES as $resource) {
            $model = $resource::getModel();
            if (in_array(class_basename($model), $accounts, true)) {
                continue;
            }
            $this->assertNotNull(ClientModules::forModel($model), $model.' has no module, so nobody can be given it');
        }

        foreach (StoreContext::SCOPED_MODELS as $model) {
            $this->assertNotNull(ClientModules::forModel($model), $model.' has no module');
        }
    }

    // ---- Settings ---------------------------------------------------------------------------

    public function test_two_clients_on_one_store_keep_separate_settings(): void
    {
        Setting::set('home', ['hero_heading' => 'Platform default']);
        $clients = app(ClientContext::class);

        $clients->resolveForPublic($this->alpha);
        $this->assertSame('Platform default', Setting::get('home.hero_heading'), 'a client starts from the platform value');
        Setting::set('home', ['hero_heading' => 'Alpha heading']);
        $this->assertSame('Alpha heading', Setting::get('home.hero_heading'));

        $clients->resolveForPublic($this->beta);
        $this->assertSame('Platform default', Setting::get('home.hero_heading'), 'another client is unaffected');

        $clients->forget();
        $this->assertSame('Platform default', Setting::get('home.hero_heading'), 'the platform value is unchanged');

        Cache::forget('settings.all');
    }

    public function test_store_settings_need_the_appearance_permission(): void
    {
        $this->actingAs($this->staff(['products.view']), 'client');
        $this->get('/client/homepage-settings')->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->staff(['appearance.update']), 'client');
        $this->get('/client/homepage-settings')->assertSuccessful();
    }

    public function test_every_client_runs_its_own_site_settings(): void
    {
        $this->actingAs($this->alphaAdmin, 'client');
        $this->get('/client/site-settings')->assertSuccessful();

        $this->flushSession();
        $this->actingAs($this->staff(['products.view']), 'client');
        $this->get('/client/site-settings')->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->staff(['appearance.update']), 'client');
        $this->get('/client/site-settings')->assertSuccessful();
    }

    public function test_no_platform_screen_links_to_the_removed_client_switcher(): void
    {
        $this->actingAs($this->operator, 'admin');

        foreach (['/', '/clients', '/client-totals'] as $path) {
            $this->get($path)->assertSuccessful()->assertDontSee('switch_client');
        }
    }

    // ---- Activity log -----------------------------------------------------------------------

    public function test_account_changes_are_logged_by_name_and_never_by_value(): void
    {
        $this->actingAs($this->operator, 'admin');

        $this->alphaAdmin->update(['password' => 'a-brand-new-secret']);

        $entry = PlatformActivity::where('subject_type', 'ClientUser')->latest('id')->firstOrFail();
        $this->assertSame('Operator', $entry->actor);
        $this->assertContains('password', $entry->fields);
        $this->assertStringNotContainsString('a-brand-new-secret', json_encode($entry->getAttributes()));
    }

    public function test_changes_without_a_signed_in_admin_are_not_logged(): void
    {
        $this->alphaAdmin->update(['phone' => '123']);

        $this->assertSame(0, PlatformActivity::count());
    }
}
