<?php

namespace Tests\Feature;

use App\Filament\Auth\EditProfile;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The "Profile" item in the user menu: each account edits its own details. */
class EditProfileTest extends TestCase
{
    use RefreshDatabase;

    private ClientUser $admin;

    private Client $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->admin = ClientUser::create([
            'client_id' => $alpha->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
    }

    public function test_the_profile_page_opens_in_both_panels(): void
    {
        $this->actingAs($this->admin, 'client')->get('/client/profile')->assertOk()->assertSee('admin@alpha.test');

        $operator = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
        $this->actingAs($operator, 'admin')->get('/profile')->assertOk()->assertSee('ops@platform.test');
    }

    public function test_the_user_menu_item_shows_the_name_and_links_to_the_profile_page(): void
    {
        Filament::setCurrentPanel('client');
        $this->actingAs($this->admin, 'client');

        $item = collect(Filament::getCurrentPanel()->getUserMenuItemGroups())->flatMap(fn (array $g) => $g)->get('profile');

        $this->assertSame('Alpha Admin', $item->getLabel());
        $this->assertStringEndsWith('/client/profile', $item->getUrl());
    }

    public function test_a_client_user_updates_their_name_and_phone(): void
    {
        Filament::setCurrentPanel('client');
        $this->actingAs($this->admin, 'client');

        Livewire::test(EditProfile::class)
            ->fillForm(['name' => 'Renamed', 'phone' => '9876543210'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed', $this->admin->fresh()->name);
        $this->assertSame('9876543210', $this->admin->fresh()->phone);
    }

    public function test_an_email_used_in_another_client_is_still_allowed(): void
    {
        ClientUser::create([
            'client_id' => $this->beta->id, 'name' => 'Beta Admin', 'email' => 'shared@example.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        Filament::setCurrentPanel('client');
        $this->actingAs($this->admin, 'client');

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'shared@example.test', 'currentPassword' => 'password123'])
            ->call('save')
            ->assertHasNoFormErrors(['email']);
    }

    public function test_an_email_taken_in_the_same_client_is_rejected(): void
    {
        ClientUser::create([
            'client_id' => $this->admin->client_id, 'name' => 'Staff', 'email' => 'staff@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'is_active' => true,
        ]);

        Filament::setCurrentPanel('client');
        $this->actingAs($this->admin, 'client');

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'staff@alpha.test', 'currentPassword' => 'password123'])
            ->call('save')
            ->assertHasFormErrors(['email' => 'unique']);
    }
}
