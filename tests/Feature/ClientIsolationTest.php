<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\Scopes\ClientOwned;
use App\Filament\Auth\Login;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
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

        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $this->store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $this->store->id, 'plan_id' => $plan->id, 'is_active' => true]);

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
        app(StoreContext::class)->resolve($this->alphaAdmin, $this->alpha->templateId());

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
        $this->get('/')->assertRedirect('/login');
        $this->get('/clients')->assertRedirect('/login');
    }

    public function test_a_customer_cannot_open_either_admin_panel(): void
    {
        $customer = Customer::create(['client_id' => $this->alpha->id, 'name' => 'Shopper', 'email' => 'shopper@alpha.test', 'password' => 'password123', 'is_active' => true]);

        $this->actingAs($customer, 'web');

        $this->get('/')->assertRedirect('/login');
        $this->get('/client')->assertRedirect('/client/login');
    }

    // ---- The same email in two stores ---------------------------------------

    public function test_the_same_staff_email_signs_into_the_store_whose_host_it_is_on(): void
    {
        $alphaStaff = ClientUser::create(['client_id' => $this->alpha->id, 'name' => 'Shared', 'email' => 'shared@example.test', 'password' => 'alpha-pass-1', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'is_active' => true]);
        $betaStaff = ClientUser::create(['client_id' => $this->beta->id, 'name' => 'Shared', 'email' => 'shared@example.test', 'password' => 'beta-pass-1', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'is_active' => true]);

        Filament::setCurrentPanel(Filament::getPanel('client'));
        request()->headers->set('host', 'beta.'.Client::baseDomain());

        // Beta's host: Alpha's password is not Beta's account, even though Alpha's row comes first.
        Livewire::test(Login::class)
            ->fillForm(['email' => 'shared@example.test', 'password' => 'alpha-pass-1'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertGuest('client');

        Livewire::test(Login::class)
            ->fillForm(['email' => 'shared@example.test', 'password' => 'beta-pass-1'])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($betaStaff, 'client');
        $this->assertNotSame($alphaStaff->id, auth('client')->id());
    }

    public function test_a_store_session_is_ended_on_another_stores_host(): void
    {
        $this->actingAs($this->alphaAdmin, 'client');

        $this->get($this->beta->publicUrl().'/client')->assertRedirect();
        $this->assertGuest('client');
    }

    public function test_a_store_session_works_on_its_own_host(): void
    {
        $this->actingAs($this->alphaAdmin, 'client');

        $this->get($this->alpha->publicUrl().'/client')->assertSuccessful();
        $this->assertAuthenticatedAs($this->alphaAdmin, 'client');
    }

    // ---- Storefront accounts ----------------------------------------------

    public function test_a_customer_of_one_client_cannot_sign_in_on_another(): void
    {
        Customer::create(['client_id' => $this->alpha->id, 'name' => 'Shopper', 'email' => 'shopper@example.test', 'phone' => '9876543210', 'password' => Hash::make('password123'), 'is_active' => true]);

        // Beta's storefront: the same phone and password must not be accepted.
        $this->withSession([Client::SESSION_KEY => 'beta'])
            ->post('/login', ['phone' => '9876543210', 'password' => 'password123'])
            ->assertSessionHasErrors('phone');
        $this->assertGuest('web');

        // Alpha's own storefront accepts it.
        $this->withSession([Client::SESSION_KEY => 'alpha'])
            ->post('/login', ['phone' => '9876543210', 'password' => 'password123'])
            ->assertRedirect(route('account.index'));
        $this->assertAuthenticated('web');
    }

    public function test_the_same_email_can_register_with_two_clients(): void
    {
        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/register', [
            'name' => 'Shopper', 'email' => 'same@example.test', 'phone' => '9876543210',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('otp.verify'));
        $this->enterOtp(session: [Client::SESSION_KEY => 'alpha'])->assertRedirect(route('account.index'));

        $this->post('/logout');

        $this->withSession([Client::SESSION_KEY => 'beta'])->post('/register', [
            'name' => 'Shopper', 'email' => 'same@example.test', 'phone' => '9876543210',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect(route('otp.verify'));
        $this->enterOtp(session: [Client::SESSION_KEY => 'beta'])->assertRedirect(route('account.index'));

        $this->assertSame(1, Customer::where('client_id', $this->alpha->id)->where('email', 'same@example.test')->count());
        $this->assertSame(1, Customer::where('client_id', $this->beta->id)->where('email', 'same@example.test')->count());
    }

    public function test_a_shopper_session_does_not_follow_the_visitor_to_another_client(): void
    {
        $customer = Customer::create(['client_id' => $this->alpha->id, 'name' => 'Shopper', 'email' => 'shopper@alpha.test', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($customer, 'web');

        // Browsing Beta's storefront in the same browser ends the Alpha session.
        $this->get($this->beta->publicUrl().'/')->assertSuccessful();

        $this->assertGuest('web');
    }

    public function test_a_reset_link_from_one_store_does_not_reset_the_same_email_on_another(): void
    {
        Notification::fake();
        $alphaShopper = Customer::create(['client_id' => $this->alpha->id, 'name' => 'Shopper', 'email' => 'same@example.test', 'password' => Hash::make('alpha-pass-1'), 'is_active' => true]);
        $betaShopper = Customer::create(['client_id' => $this->beta->id, 'name' => 'Shopper', 'email' => 'same@example.test', 'password' => Hash::make('beta-pass-1'), 'is_active' => true]);

        // Both stores send a link (forgot password now goes by WhatsApp; emailed links remain for
        // a past guest who registers); asking the second store must not cancel the first one's.
        Password::broker('customers')->sendResetLink(['email' => 'same@example.test', 'client_id' => $this->alpha->id, 'is_active' => true]);
        Password::broker('customers')->sendResetLink(['email' => 'same@example.test', 'client_id' => $this->beta->id, 'is_active' => true]);

        $tokens = [];
        Notification::assertSentTo($alphaShopper, ResetPassword::class, function ($n) use (&$tokens) {
            $tokens['alpha'] = $n->token;

            return true;
        });
        Notification::assertSentTo($betaShopper, ResetPassword::class, function ($n) use (&$tokens, $betaShopper) {
            $tokens['beta'] = $n->token;
            // The emailed link carries the plain address, so the reset form it opens can be submitted as filled in.
            parse_str((string) parse_url($n->toMail($betaShopper)->actionUrl, PHP_URL_QUERY), $query);
            $this->assertSame('same@example.test', $query['email'] ?? null);

            return true;
        });

        // Beta's link opened on Alpha's storefront resets nothing.
        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/reset-password', [
            'token' => $tokens['beta'], 'email' => 'same@example.test',
            'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('alpha-pass-1', $alphaShopper->fresh()->password));

        // Each link works on its own store, and only for that store's account.
        $this->withSession([Client::SESSION_KEY => 'beta'])->post('/reset-password', [
            'token' => $tokens['beta'], 'email' => 'same@example.test',
            'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('newpass123', $betaShopper->fresh()->password));
        $this->assertTrue(Hash::check('alpha-pass-1', $alphaShopper->fresh()->password));

        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/reset-password', [
            'token' => $tokens['alpha'], 'email' => 'same@example.test',
            'password' => 'alphanew123', 'password_confirmation' => 'alphanew123',
        ])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('alphanew123', $alphaShopper->fresh()->password));
    }

    public function test_the_order_history_counts_only_the_orders_store(): void
    {
        $make = fn (Client $client, string $number) => Order::withoutGlobalScopes()->create([
            'client_id' => $client->id, 'template' => 'fashion', 'number' => $number,
            'email' => 'same@example.test', 'status' => 'confirmed', 'shipping_name' => 'Shopper',
            'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
            'shipping_postal_code' => '400001', 'shipping_country' => 'India', 'subtotal' => 1000, 'total' => 1000,
        ]);
        $order = $make($this->alpha, 'ME-TEST-0101');
        $make($this->beta, 'ME-TEST-0102');
        $make($this->beta, 'ME-TEST-0103');

        // A platform session sees every store's orders; the history must still be Alpha's alone.
        $history = (new \ReflectionMethod(OrderInfolist::class, 'history'))->invoke(null, $order);

        $this->assertStringContainsString('First order', $history);
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
        $this->withSession([Client::SESSION_KEY => 'beta'])
            ->get('/order/'.$order->number.'/confirmation')
            ->assertNotFound();

        $this->assertSame($this->alpha->id, $order->fresh()->client_id);
        $this->assertSame($product->client_id, $order->client_id);
    }

    public function test_a_deactivated_store_says_so_when_its_team_signs_in(): void
    {
        $this->alpha->update(['is_active' => false]);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        request()->headers->set('host', 'alpha.'.Client::baseDomain());

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@alpha.test', 'password' => 'password123'])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => 'This store has been deactivated, so its team cannot sign in. Please contact support.']);
        $this->assertGuest('client');

        // A wrong password still gets the generic message: nothing is given away.
        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@alpha.test', 'password' => 'wrong-password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => __('filament-panels::auth/pages/login.messages.failed')]);
    }

    public function test_a_deactivated_team_member_is_told_so(): void
    {
        $this->alphaAdmin->update(['is_active' => false]);
        Filament::setCurrentPanel(Filament::getPanel('client'));
        request()->headers->set('host', 'alpha.'.Client::baseDomain());

        Livewire::test(Login::class)
            ->fillForm(['email' => 'admin@alpha.test', 'password' => 'password123'])
            ->call('authenticate')
            ->assertHasFormErrors(['email' => "Your account has been deactivated. Please contact your store's Client Admin."]);
        $this->assertGuest('client');
    }

    public function test_an_open_session_is_signed_out_with_the_reason_when_the_store_is_deactivated(): void
    {
        $this->actingAs($this->alphaAdmin, 'client');
        $this->alpha->update(['is_active' => false]);

        $this->get($this->alpha->publicUrl().'/client')
            ->assertRedirect(Filament::getPanel('client')->getLoginUrl())
            ->assertSessionHas('filament.notifications', fn (array $n) => str_contains($n[0]['body'] ?? '', 'This store has been deactivated'));
        $this->assertGuest('client');
    }

    public function test_an_open_session_is_signed_out_with_the_reason_when_the_account_is_deactivated(): void
    {
        $this->actingAs($this->alphaAdmin, 'client');
        $this->alphaAdmin->update(['is_active' => false]);

        $this->get($this->alpha->publicUrl().'/client')
            ->assertRedirect(Filament::getPanel('client')->getLoginUrl())
            ->assertSessionHas('filament.notifications', fn (array $n) => str_contains($n[0]['body'] ?? '', 'Your account has been deactivated'));
        $this->assertGuest('client');
    }

    public function test_a_deactivated_stores_address_says_so_instead_of_leading_to_the_panel(): void
    {
        $this->alpha->update(['is_active' => false]);

        $this->get($this->alpha->publicUrl().'/')
            ->assertStatus(503)
            ->assertSee('This store has been deactivated')
            ->assertSee($this->alpha->name);
        $this->get($this->alpha->publicUrl().'/shop')->assertStatus(503);

        // An address that belongs to no client still goes to the platform panel.
        $this->get('http://nobody.'.Client::baseDomain().'/')->assertRedirect();
    }
}
