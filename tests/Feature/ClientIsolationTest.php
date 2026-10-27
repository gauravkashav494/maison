<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Clients\Scopes\ClientOwned;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Two clients running the SAME store must not see each other's data.
 *
 * This is the case the platform got wrong before clients existed, so it is tested from
 * several directions: the resolved context, the query scope, the policies, the admin panel
 * over HTTP, and the storefront's own customer accounts.
 */
class ClientIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Storefront $store;

    private Client $alpha;

    private Client $beta;

    private ClientUser $alphaAdmin;

    private ClientUser $betaAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);

        // One store, two clients on it — the exact situation that used to leak.
        $this->store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);

        $this->alpha = Client::create(['name' => 'Alpha', 'slug' => 'alpha', 'storefront_id' => $this->store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'slug' => 'beta', 'storefront_id' => $this->store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->alphaAdmin = ClientUser::create(['client_id' => $this->alpha->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->betaAdmin = ClientUser::create(['client_id' => $this->beta->id, 'name' => 'Beta Admin', 'email' => 'admin@beta.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
    }

    private function product(Client $client, string $name): Product
    {
        return Product::withoutGlobalScopes()->create([
            'client_id' => $client->id,
            'template' => $client->templateId(),
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'price' => 1000,
            'is_active' => true,
        ]);
    }

    // ---- The resolved context ---------------------------------------------

    public function test_a_client_staff_account_cannot_choose_another_client(): void
    {
        $context = app(ClientContext::class);

        // A client id supplied with the request is ignored for a staff account.
        $context->resolveForIdentity($this->alphaAdmin, $this->beta->id);

        $this->assertSame($this->alpha->id, $context->id());
        $this->assertTrue($context->isLocked());
        $this->assertFalse($context->isSuperAdmin());
        $this->assertFalse($context->owns($this->beta->id));
        $this->assertTrue($context->owns($this->alpha->id));
    }

    public function test_an_unknown_or_inactive_client_id_selects_nothing_for_a_super_admin(): void
    {
        $superAdmin = User::create(['name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123', 'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true]);
        $context = app(ClientContext::class);

        $context->resolveForIdentity($superAdmin, 99999);
        $this->assertNull($context->id());

        $this->beta->update(['is_active' => false]);
        $context->resolveForIdentity($superAdmin, $this->beta->id);
        $this->assertNull($context->id());
    }

    public function test_a_disabled_account_resolves_to_no_client(): void
    {
        $this->alphaAdmin->update(['is_active' => false]);

        app(ClientContext::class)->resolveForIdentity($this->alphaAdmin->fresh());

        $this->assertNull(app(ClientContext::class)->id());
    }

    // ---- The query scope ---------------------------------------------------

    public function test_the_client_scope_hides_rows_of_another_client_on_the_same_store(): void
    {
        $mine = $this->product($this->alpha, 'Alpha Coat');
        $theirs = $this->product($this->beta, 'Beta Coat');

        Product::addGlobalScope(ClientOwned::NAME, new ClientOwned($this->alpha->id));

        $this->assertEqualsCanonicalizing([$mine->id], Product::pluck('id')->all());
        $this->assertNull(Product::find($theirs->id), 'A product of another client must not be findable by id.');
        $this->assertSame(1, Product::count());
    }

    public function test_saving_stamps_the_resolved_client_and_ignores_a_submitted_one(): void
    {
        app(ClientContext::class)->resolveForIdentity($this->alphaAdmin);

        $product = new Product([
            'name' => 'Posted Coat',
            'slug' => 'posted-coat',
            'price' => 500,
            'client_id' => $this->beta->id, // attacker-supplied
        ]);
        $product->save();

        $this->assertSame($this->alpha->id, $product->fresh()->client_id);
    }

    // ---- The policies ------------------------------------------------------

    public function test_policies_refuse_a_record_of_another_client(): void
    {
        $theirs = $this->product($this->beta, 'Beta Coat');
        app(ClientContext::class)->resolveForIdentity($this->alphaAdmin);
        app(\App\Admin\StoreContext::class)->resolve($this->alphaAdmin, $this->alpha->templateId());

        $this->assertFalse($this->alphaAdmin->can('view', $theirs));
        $this->assertFalse($this->alphaAdmin->can('update', $theirs));
        $this->assertFalse($this->alphaAdmin->can('delete', $theirs));
    }

    public function test_a_client_admin_cannot_manage_another_clients_staff(): void
    {
        app(ClientContext::class)->resolveForIdentity($this->alphaAdmin);

        $this->assertTrue($this->alphaAdmin->can('update', $this->alphaAdmin));
        $this->assertFalse($this->alphaAdmin->can('update', $this->betaAdmin));
        $this->assertFalse($this->alphaAdmin->can('delete', $this->betaAdmin));
    }

    public function test_admin_staff_cannot_manage_staff_accounts_at_all(): void
    {
        $staff = ClientUser::create(['client_id' => $this->alpha->id, 'name' => 'Helper', 'email' => 'helper@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'is_active' => true]);
        app(ClientContext::class)->resolveForIdentity($staff);

        $this->assertFalse($staff->can('viewAny', ClientUser::class));
        $this->assertFalse($staff->can('update', $this->alphaAdmin));
    }

    public function test_a_client_account_is_never_a_super_admin(): void
    {
        $this->assertFalse($this->alphaAdmin->isSuperAdmin());
        $this->assertFalse($this->alphaAdmin->can('viewAny', User::class));
        $this->assertFalse($this->alphaAdmin->can('viewAny', Client::class));
    }

    // ---- The panels over HTTP ---------------------------------------------

    public function test_the_client_panel_does_not_serve_another_clients_record(): void
    {
        $theirs = $this->product($this->beta, 'Beta Coat');
        $mine = $this->product($this->alpha, 'Alpha Coat');

        $this->actingAs($this->alphaAdmin, 'client');

        $this->get('/client/products/'.$mine->id.'/edit')->assertSuccessful();
        $this->get('/client/products/'.$theirs->id.'/edit')->assertNotFound();
    }

    public function test_a_client_account_cannot_open_the_super_admin_panel(): void
    {
        $this->actingAs($this->alphaAdmin, 'client');

        // The admin panel uses its own guard, so this session is simply not signed in there.
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/clients')->assertRedirect('/admin/login');
    }

    public function test_a_customer_cannot_open_either_admin_panel(): void
    {
        $customer = Customer::create(['client_id' => $this->alpha->id, 'name' => 'Shopper', 'email' => 'shopper@alpha.test', 'password' => 'password123', 'is_active' => true]);

        $this->actingAs($customer, 'web');

        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/client')->assertRedirect('/client/login');
    }

    // ---- Storefront accounts ----------------------------------------------

    public function test_a_customer_of_one_client_cannot_sign_in_on_another(): void
    {
        Customer::create(['client_id' => $this->alpha->id, 'name' => 'Shopper', 'email' => 'shopper@example.test', 'password' => Hash::make('password123'), 'is_active' => true]);

        // Beta's storefront: the same address and password must not be accepted.
        $this->withSession([\App\Models\Client::SESSION_KEY => 'beta'])
            ->post('/login', ['email' => 'shopper@example.test', 'password' => 'password123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');

        // Alpha's own storefront accepts it.
        $this->withSession([\App\Models\Client::SESSION_KEY => 'alpha'])
            ->post('/login', ['email' => 'shopper@example.test', 'password' => 'password123'])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticated('web');
    }

    public function test_the_same_email_can_register_with_two_clients(): void
    {
        $this->withSession([\App\Models\Client::SESSION_KEY => 'alpha'])->post('/register', [
            'name' => 'Shopper', 'email' => 'same@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('account.index'));

        $this->post('/logout');

        $this->withSession([\App\Models\Client::SESSION_KEY => 'beta'])->post('/register', [
            'name' => 'Shopper', 'email' => 'same@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('account.index'));

        $this->assertSame(1, Customer::where('client_id', $this->alpha->id)->where('email', 'same@example.test')->count());
        $this->assertSame(1, Customer::where('client_id', $this->beta->id)->where('email', 'same@example.test')->count());
    }

    public function test_a_shopper_session_does_not_follow_the_visitor_to_another_client(): void
    {
        $customer = Customer::create(['client_id' => $this->alpha->id, 'name' => 'Shopper', 'email' => 'shopper@alpha.test', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($customer, 'web');

        // Browsing Beta's storefront in the same browser ends the Alpha session.
        $this->withSession([\App\Models\Client::SESSION_KEY => 'beta'])->get('/')->assertSuccessful();

        $this->assertGuest('web');
    }

    public function test_an_order_of_one_client_is_not_visible_on_another(): void
    {
        $product = $this->product($this->alpha, 'Alpha Coat');
        $order = Order::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id,
            'template' => 'fashion',
            'number' => 'ME-TEST-0001',
            'email' => 'shopper@alpha.test',
            'status' => 'confirmed',
            'shipping_name' => 'Shopper',
            'shipping_line1' => '1 Test Street',
            'shipping_city' => 'Mumbai',
            'shipping_state' => 'MH',
            'shipping_postal_code' => '400001',
            'shipping_country' => 'India',
            'subtotal' => 1000, 'total' => 1000,
        ]);

        // Beta's storefront must not confirm, or even acknowledge, Alpha's order.
        $this->withSession([\App\Models\Client::SESSION_KEY => 'beta'])
            ->get('/order/'.$order->number.'/confirmation')
            ->assertNotFound();

        $this->assertSame($this->alpha->id, $order->fresh()->client_id);
        $this->assertSame($product->client_id, $order->client_id);
    }
}
