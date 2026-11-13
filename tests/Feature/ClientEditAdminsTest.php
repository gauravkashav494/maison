<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Filament\Resources\ClientUsers\ClientUserResource;
use App\Filament\Resources\ClientUsers\Pages\CreateClientUser;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** The edit-client wizard's "Client admins" step: edit, add and keep at least one active admin. */
class ClientEditAdminsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private ClientUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::firstOrCreate(['slug' => 'fashion'], ['name' => 'Fashion Store', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Acme', 'subdomain' => 'acme', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Old Name', 'email' => 'owner@acme.test', 'password' => 'secret-pass', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);

        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function editPage()
    {
        return Livewire::test(EditClient::class, ['record' => $this->client->getRouteKey()]);
    }

    public function test_an_admin_is_edited_and_keeps_their_password_when_it_is_left_blank(): void
    {
        $this->editPage()
            ->set('data.clientAdmins.record-'.$this->admin->id.'.name', 'New Name')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->admin->refresh();
        $this->assertSame('New Name', $this->admin->name);
        $this->assertTrue(Hash::check('secret-pass', $this->admin->password));
    }

    public function test_a_new_admin_is_added_as_a_client_admin_of_this_client(): void
    {
        $page = $this->editPage();
        $items = $page->get('data.clientAdmins');
        $items['new-one'] = ['name' => 'Second', 'email' => 'second@acme.test', 'phone' => null, 'password' => 'another-pass', 'is_active' => true];

        $page->set('data.clientAdmins', $items)->call('save')->assertHasNoFormErrors();

        $this->assertDatabaseHas('client_users', ['email' => 'second@acme.test', 'client_id' => $this->client->id, 'role' => ClientUser::ROLE_CLIENT_ADMIN]);
    }

    public function test_the_client_cannot_be_left_without_an_active_admin(): void
    {
        $this->editPage()
            ->set('data.clientAdmins.record-'.$this->admin->id.'.is_active', false)
            ->call('save');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_an_email_already_used_by_another_account_of_this_client_is_refused(): void
    {
        ClientUser::create(['client_id' => $this->client->id, 'name' => 'Staff', 'email' => 'staff@acme.test', 'password' => 'secret-pass', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'is_active' => true]);

        $this->editPage()
            ->set('data.clientAdmins.record-'.$this->admin->id.'.email', 'staff@acme.test')
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame('owner@acme.test', $this->admin->fresh()->email);
    }

    public function test_a_deleted_admin_is_restored_from_the_edit_page(): void
    {
        $second = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Second', 'email' => 'second@acme.test', 'password' => 'secret-pass', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $second->delete();

        $this->editPage()
            ->assertActionVisible('restoreAdmins')
            ->callAction('restoreAdmins', ['admins' => [$second->id]])
            ->assertHasNoActionErrors()
            ->assertSet('data.clientAdmins.record-'.$second->id.'.email', 'second@acme.test');

        $this->assertFalse($second->fresh()->trashed());
    }

    public function test_restore_admins_is_hidden_when_nothing_is_in_the_trash(): void
    {
        $this->editPage()->assertActionHidden('restoreAdmins');
    }

    public function test_client_admins_is_not_in_the_super_admin_menu(): void
    {
        app(ClientContext::class)->resolveForIdentity(auth('admin')->user());

        $this->assertFalse(ClientUserResource::shouldRegisterNavigation());
    }

    public function test_staff_stays_in_the_client_admin_menu(): void
    {
        $this->actingAs($this->admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($this->admin);

        $this->assertTrue(ClientUserResource::shouldRegisterNavigation());
    }

    public function test_an_email_used_in_another_client_is_allowed(): void
    {
        $other = Client::create(['name' => 'Other', 'subdomain' => 'other', 'storefront_id' => $this->client->storefront_id, 'plan_id' => $this->client->plan_id, 'is_active' => true]);
        ClientUser::create(['client_id' => $other->id, 'name' => 'Elsewhere', 'email' => 'shared@mail.test', 'password' => 'secret-pass', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);

        $page = $this->editPage();
        $items = $page->get('data.clientAdmins');
        $items['new-one'] = ['name' => 'Same Email', 'email' => 'shared@mail.test', 'phone' => null, 'password' => 'another-pass', 'is_active' => true];
        $page->set('data.clientAdmins', $items)->call('save')->assertHasNoFormErrors();

        $this->assertDatabaseHas('client_users', ['email' => 'shared@mail.test', 'client_id' => $this->client->id]);
        $this->assertDatabaseHas('client_users', ['email' => 'shared@mail.test', 'client_id' => $other->id]);
    }

    public function test_several_admins_are_added_in_one_save(): void
    {
        $page = $this->editPage();
        $items = $page->get('data.clientAdmins');
        $items['new-one'] = ['name' => 'Second', 'email' => 'second@acme.test', 'phone' => null, 'password' => 'another-pass', 'is_active' => true];
        $items['new-two'] = ['name' => 'Third', 'email' => 'third@acme.test', 'phone' => null, 'password' => 'another-pass', 'is_active' => true];
        $page->set('data.clientAdmins', $items)->call('save')->assertHasNoFormErrors();

        $this->assertSame(3, $this->client->clientAdmins()->count());
    }

    public function test_a_client_admin_adds_another_client_admin_from_staff(): void
    {
        $this->actingAs($this->admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($this->admin);

        Livewire::test(CreateClientUser::class)
            ->fillForm(['name' => 'Co Owner', 'email' => 'co@acme.test', 'password' => 'another-pass', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('client_users', ['email' => 'co@acme.test', 'client_id' => $this->client->id, 'role' => ClientUser::ROLE_CLIENT_ADMIN]);
    }
}
