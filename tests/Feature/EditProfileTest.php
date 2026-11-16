<?php

namespace Tests\Feature;

use App\Filament\Auth\EditProfile;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\User;
use App\Support\PhoneNumber;
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
        $this->assertSame('+91 98765 43210', $this->admin->fresh()->phone);
    }

    public function test_the_phone_is_checked_against_its_country(): void
    {
        Filament::setCurrentPanel('client');
        $this->actingAs($this->admin, 'client');

        // The field holds "+<dial code> <number>"; the country picker sets the code.
        // India only for now: another country's code is refused.
        // (Three through the form: the profile page allows only 5 saves a minute; the rest below.)
        foreach (['+91 12345', '+91 98765 432101', '+44 7400 123456'] as $invalid) {
            Livewire::test(EditProfile::class)
                ->fillForm(['phone' => $invalid])
                ->call('save')
                ->assertHasFormErrors(['phone']);
        }

        foreach (['+91 05876 5432', '+91 1800 1234567', '+999 1234567', '98765'] as $invalid) {
            $this->assertNull(PhoneNumber::normalise($invalid), $invalid);
        }

        // Spaces, dashes and a trunk 0 are fine; it is saved in one shape.
        Livewire::test(EditProfile::class)
            ->fillForm(['phone' => '+91 098765-43210'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('+91 98765 43210', $this->admin->fresh()->phone);
    }

    /** Its own test: the profile page allows only 5 saves a minute. */
    public function test_a_blank_phone_clears_the_number(): void
    {
        $this->admin->update(['phone' => '+91 98765 43210']);
        Filament::setCurrentPanel('client');
        $this->actingAs($this->admin->fresh(), 'client');

        Livewire::test(EditProfile::class)
            ->fillForm(['phone' => ''])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($this->admin->fresh()->phone);
    }

    public function test_the_same_check_applies_to_the_other_phone_fields(): void
    {
        $operator = User::create(['name' => 'Ops', 'email' => 'ops2@platform.test', 'password' => 'password123', 'is_super_admin' => true, 'is_active' => true]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($operator, 'admin');

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Support', 'email' => 'support@platform.test', 'password' => 'password123', 'is_super_admin' => true, 'phone' => '+91 12'])
            ->call('create')
            ->assertHasFormErrors(['phone']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Support', 'email' => 'support@platform.test', 'password' => 'password123', 'is_super_admin' => true, 'phone' => '+91 98765 43210'])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame('+91 98765 43210', User::where('email', 'support@platform.test')->value('phone'));
    }

    public function test_a_saved_phone_opens_with_its_country(): void
    {
        $this->admin->update(['phone' => '+91 98765 43210']);
        Filament::setCurrentPanel('client');
        $this->actingAs($this->admin->fresh(), 'client');

        // The code is shown fixed inside the box: with India only there is no dropdown. The number
        // box takes digits only, 10 at most (tidied in the browser, checked again on save).
        Livewire::test(EditProfile::class)
            ->assertSchemaStateSet(['phone' => '+91 98765 43210'])
            ->assertSee('fi-phone-country is-fixed', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertDontSee('fi-phone-panel', false);
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
