<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SuperAdmin panel: what the platform operator can reach, and what switching client does.
 *
 * The switcher is the one place a client is chosen from the request, so it is checked here:
 * only an existing, active client id is accepted, and once chosen it narrows every content
 * query exactly as a client's own session is narrowed.
 */
class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private Client $alpha;

    private Client $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);

        $this->alpha = Client::create(['name' => 'Alpha', 'slug' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'slug' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->operator = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        foreach ([$this->alpha, $this->beta] as $client) {
            Product::withoutGlobalScopes()->create([
                'client_id' => $client->id, 'template' => 'fashion',
                'name' => $client->name.' Coat', 'slug' => $client->slug.'-coat', 'price' => 1000, 'is_active' => true,
            ]);
        }
    }

    public function test_the_operator_can_open_the_platform_modules(): void
    {
        $this->actingAs($this->operator, 'admin');

        $this->get('/admin')->assertSuccessful();
        $this->get('/admin/clients')->assertSuccessful();
        $this->get('/admin/plans')->assertSuccessful();
        $this->get('/admin/storefronts')->assertSuccessful();
        $this->get('/admin/users')->assertSuccessful();
        $this->get('/admin/customers')->assertSuccessful();
        $this->get('/admin/client-users')->assertSuccessful();
        $this->get('/admin/products')->assertSuccessful();
    }

    public function test_switching_client_narrows_the_content_and_only_accepts_a_real_client(): void
    {
        $this->actingAs($this->operator, 'admin');

        $this->get('/admin/products?switch_client='.$this->alpha->id)->assertRedirect('/admin/products');
        $this->assertSame($this->alpha->id, session('admin.client'));
        $this->get('/admin/products')->assertSuccessful()->assertSee('Alpha Coat')->assertDontSee('Beta Coat');

        // An id that names no client clears the choice instead of selecting something else.
        $this->get('/admin/products?switch_client=99999')->assertRedirect('/admin/products');
        $this->assertNull(session('admin.client'));

        // An inactive client is not selectable either.
        $this->beta->update(['is_active' => false]);
        $this->get('/admin/products?switch_client='.$this->beta->id)->assertRedirect('/admin/products');
        $this->get('/admin/products')->assertSuccessful()->assertSee('Alpha Coat');
    }

    public function test_a_client_staff_account_cannot_switch_client_through_the_url(): void
    {
        $staff = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        $this->actingAs($staff, 'client');

        // The parameter is ignored: no redirect, no session entry, still Alpha's data only.
        $this->get('/client/products?switch_client='.$this->beta->id)
            ->assertSuccessful()
            ->assertSee('Alpha Coat')
            ->assertDontSee('Beta Coat');
        $this->assertNull(session('admin.client'));
    }

    public function test_the_client_panel_has_no_platform_modules(): void
    {
        $staff = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        $this->actingAs($staff, 'client');

        // These routes only exist on /admin.
        $this->get('/client/clients')->assertNotFound();
        $this->get('/client/plans')->assertNotFound();
        $this->get('/client/users')->assertNotFound();
        $this->get('/client/storefronts')->assertNotFound();
        $this->get('/client/templates')->assertNotFound();
    }

    public function test_a_disabled_operator_is_refused_by_the_panel(): void
    {
        $this->operator->update(['is_active' => false]);
        $this->actingAs($this->operator->fresh(), 'admin');

        // Filament refuses the panel itself (canAccessPanel), so the session is of no use.
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/clients')->assertForbidden();
    }
}
