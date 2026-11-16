<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\ClientUsers\Pages\ListClientUsers;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The readable password copy: kept whenever a plain password is set, never replaced by a
 * hash, never serialised, and shown only to a SuperAdmin or a Client Admin.
 */
class PasswordHintTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private User $operator;

    private ClientUser $clientAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->operator = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'operator-pass',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);

        $this->clientAdmin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'admin-pass-1', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
    }

    private function enterClientPanel(ClientUser $user): void
    {
        $this->actingAs($user, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($user);
        app(StoreContext::class)->resolve($user, app(ClientContext::class)->templateId());
    }

    public function test_the_plain_password_is_kept_on_create_and_change(): void
    {
        $customer = Customer::create(['client_id' => $this->client->id, 'name' => 'Shopper', 'email' => 's@alpha.test', 'password' => 'shop-pass-1', 'is_active' => true]);

        $this->assertSame('operator-pass', $this->operator->fresh()->password_hint);
        $this->assertSame('admin-pass-1', $this->clientAdmin->fresh()->password_hint);
        $this->assertSame('shop-pass-1', $customer->fresh()->password_hint);
        $this->assertTrue(Hash::check('shop-pass-1', $customer->fresh()->password));

        $customer->forceFill(['password' => 'shop-pass-2'])->save();
        $this->assertSame('shop-pass-2', $customer->fresh()->password_hint);

        // An already-hashed value does not overwrite the copy.
        $customer->forceFill(['password' => Hash::make('other')])->save();
        $this->assertSame('shop-pass-2', $customer->fresh()->password_hint);

        $this->assertArrayNotHasKey('password_hint', $customer->fresh()->toArray());
    }

    public function test_a_super_admin_sees_the_column(): void
    {
        $this->actingAs($this->operator, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app(ClientContext::class)->resolveForIdentity($this->operator);

        Livewire::test(ListUsers::class)->assertTableColumnVisible('password_hint');
        Livewire::test(ListClientUsers::class)->assertTableColumnVisible('password_hint');
    }

    public function test_a_client_admin_sees_the_column(): void
    {
        $this->enterClientPanel($this->clientAdmin);

        Livewire::test(ListClientUsers::class)->assertTableColumnVisible('password_hint');
        Livewire::test(ListCustomers::class)->assertTableColumnVisible('password_hint');
    }

    public function test_admin_staff_do_not_see_the_column(): void
    {
        $role = ClientRole::create(['client_id' => $this->client->id, 'name' => 'Sales', 'permissions' => ['customers.view']]);
        $staff = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Staff', 'email' => 'staff@alpha.test', 'password' => 'staff-pass',
            'role' => ClientUser::ROLE_ADMIN_STAFF, 'client_role_id' => $role->id, 'is_active' => true,
        ]);
        $this->enterClientPanel($staff);

        Livewire::test(ListCustomers::class)->assertTableColumnHidden('password_hint');
    }
}
