<?php

namespace Tests\Feature;

use App\Admin\PlatformModules;
use App\Exceptions\LastSuperAdminException;
use App\Filament\Auth\Login;
use App\Filament\Resources\PlatformActivities\Pages\ListPlatformActivities;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PlatformActivity;
use App\Models\Storefront;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Platform accounts that are not Super Admins: they sign in to /admin and reach only the
 * modules ticked on their account. Platform accounts and platform settings stay Super Admin only.
 */
class PlatformStaffTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->operator = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'is_super_admin' => true, 'is_active' => true,
        ]);
    }

    private function staff(array $modules): User
    {
        return User::create([
            'name' => 'Support', 'email' => 'support@platform.test', 'password' => 'password123',
            'is_super_admin' => false, 'admin_modules' => $modules, 'is_active' => true,
        ]);
    }

    public function test_the_flag_decides_super_admin_and_keeps_role_in_step(): void
    {
        $this->assertTrue($this->operator->isSuperAdmin());
        $this->assertSame(User::ROLE_SUPER_ADMIN, $this->operator->role);

        $staff = $this->staff([PlatformModules::PLANS, 'not-a-module']);
        $this->assertFalse($staff->isSuperAdmin());
        $this->assertSame(User::ROLE_PLATFORM_STAFF, $staff->role);
        $this->assertSame([PlatformModules::PLANS], $staff->admin_modules);

        // Older code that only sets the role still produces a Super Admin.
        $legacy = User::create(['name' => 'Legacy', 'email' => 'legacy@platform.test', 'password' => 'password123', 'role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        $this->assertTrue($legacy->isSuperAdmin());
    }

    public function test_platform_staff_without_a_module_are_welcomed_and_told_who_to_ask(): void
    {
        // Only older accounts can have no module: the form requires one.
        $this->actingAs($this->staff([]), 'admin');

        $this->get('/')->assertSuccessful()
            ->assertSee('Hello, Support')
            ->assertSee("haven't been given access to any section yet", false)
            ->assertSee('Operator')->assertSee('ops@platform.test')
            ->assertDontSee('Revenue, all clients');
    }

    public function test_the_dashboard_shows_only_the_tiles_of_the_staff_members_modules(): void
    {
        $this->actingAs($this->staff([PlatformModules::PLANS]), 'admin');

        $this->get('/')->assertSuccessful()
            ->assertSee('Hello, Support')->assertSee('You have access to Plans.')
            ->assertSee('Most clients on Starter (1)')
            ->assertDontSee('New this month')->assertDontSee('Revenue, all clients')
            ->assertDontSee('used by a store')->assertDontSee('Recent clients');
    }

    public function test_clients_staff_no_longer_see_platform_revenue_or_stores(): void
    {
        $this->actingAs($this->staff([PlatformModules::CLIENTS]), 'admin');

        $this->get('/')->assertSuccessful()
            ->assertSee('New this month')
            ->assertDontSee('Revenue, all clients')->assertDontSee('fi-admin-stat-label">Stores<', false)->assertDontSee('Most clients on');
    }

    public function test_a_super_admin_sees_every_tile(): void
    {
        $this->actingAs($this->operator, 'admin');

        $this->get('/')->assertSuccessful()
            ->assertSee('You are a super admin')
            ->assertSee('New this month')->assertSee('Revenue, all clients')->assertSee('fi-admin-stat-label">Stores<', false)
            ->assertSee('Most clients on')->assertSee('used by a store');
    }

    public function test_platform_staff_reach_only_their_modules(): void
    {
        $this->actingAs($this->staff([PlatformModules::PLANS, PlatformModules::CLIENTS]), 'admin');

        $this->get('/')->assertSuccessful();
        $this->get('/plans')->assertSuccessful();
        $this->get('/clients')->assertSuccessful();
        $this->get('/client-users')->assertSuccessful();

        $this->get('/storefronts')->assertForbidden();
        $this->get('/client-totals')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/platform-settings')->assertForbidden();
    }

    public function test_the_activity_log_shows_staff_only_their_own_lines(): void
    {
        $staff = $this->staff([PlatformModules::PLANS]);

        $this->actingAs($this->operator, 'admin');
        Plan::create(['name' => 'Made By Operator', 'slug' => 'operator-plan', 'price_label' => '₹1', 'image_limit' => 5]);

        $this->actingAs($staff, 'admin');
        Plan::create(['name' => 'Made By Support', 'slug' => 'support-plan', 'price_label' => '₹1', 'image_limit' => 5]);

        $this->get('/platform-activities')->assertSuccessful()
            ->assertSee('Made By Support')
            ->assertDontSee('Made By Operator');

        $this->flushSession(); // a new sign-in, as AuthenticateSession ties the session to one account
        $this->actingAs($this->operator, 'admin');
        // A Super Admin starts on their own activity and uses the header button to see everyone's.
        Livewire::test(ListPlatformActivities::class)
            ->assertCanSeeTableRecords(PlatformActivity::where('subject', 'Made By Operator')->get())
            ->assertCanNotSeeTableRecords(PlatformActivity::where('subject', 'Made By Support')->get())
            ->callAction('toggleEveryone')
            ->assertCanSeeTableRecords(PlatformActivity::whereIn('subject', ['Made By Operator', 'Made By Support'])->get())
            ->callAction('toggleEveryone')
            ->assertCanNotSeeTableRecords(PlatformActivity::where('subject', 'Made By Support')->get());

        // Platform staff never get the button, and ?everyone=1 changes nothing for them.
        $this->flushSession();
        $this->actingAs(User::where('email', 'support@platform.test')->first(), 'admin');
        Livewire::withQueryParams(['everyone' => 1])->test(ListPlatformActivities::class)
            ->assertActionHidden('toggleEveryone')
            ->assertCanNotSeeTableRecords(PlatformActivity::where('subject', 'Made By Operator')->get());
    }

    public function test_a_super_admin_can_create_platform_staff_with_modules(): void
    {
        $this->actingAs($this->operator, 'admin');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Support', 'email' => 'support@platform.test', 'password' => 'password123',
                'is_super_admin' => false, 'admin_modules' => [PlatformModules::STOREFRONTS], 'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'support@platform.test')->firstOrFail();
        $this->assertFalse($user->isSuperAdmin());
        $this->assertSame([PlatformModules::STOREFRONTS], $user->admin_modules);
    }

    public function test_platform_staff_need_at_least_one_module(): void
    {
        $this->actingAs($this->operator, 'admin');

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Support', 'email' => 'support@platform.test', 'password' => 'password123',
                'is_super_admin' => false, 'admin_modules' => [], 'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['admin_modules' => 'required']);
    }

    public function test_a_super_admin_cannot_drop_their_own_super_admin_access(): void
    {
        $this->actingAs($this->operator, 'admin');

        Livewire::test(EditUser::class, ['record' => $this->operator->getKey()])
            ->fillForm(['is_super_admin' => false, 'admin_modules' => [PlatformModules::PLANS]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->operator->fresh()->isSuperAdmin());
    }

    public function test_bulk_delete_removes_other_super_admins_but_never_yourself(): void
    {
        $other = User::create(['name' => 'Second', 'email' => 'second@platform.test', 'password' => 'password123', 'is_super_admin' => true, 'is_active' => true]);
        $staff = $this->staff([PlatformModules::PLANS]);
        $this->actingAs($this->operator, 'admin');

        Livewire::test(ListUsers::class)
            ->selectTableRecords([$this->operator->id, $other->id, $staff->id])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertNotSoftDeleted($this->operator);
        $this->assertSoftDeleted($other);
        $this->assertSoftDeleted($staff);
    }

    public function test_signing_in_skips_a_remembered_page_the_account_cannot_open(): void
    {
        $this->staff([PlatformModules::PLANS]);

        // Session started on a Super Admin page, then platform staff sign in.
        $this->get('/users')->assertRedirect('/login');
        Livewire::test(Login::class)
            ->fillForm(['email' => 'support@platform.test', 'password' => 'password123'])
            ->call('authenticate')
            ->assertRedirect(url('/'));

        // A page they may open is still honoured.
        auth('admin')->logout();
        $this->flushSession();
        $this->get('/plans')->assertRedirect('/login');
        Livewire::test(Login::class)
            ->fillForm(['email' => 'support@platform.test', 'password' => 'password123'])
            ->call('authenticate')
            ->assertRedirect(url('/plans'));
    }

    public function test_the_last_active_super_admin_cannot_be_demoted_deactivated_or_deleted(): void
    {
        foreach ([
            fn () => $this->operator->fresh()->update(['is_super_admin' => false, 'admin_modules' => [PlatformModules::PLANS]]),
            fn () => $this->operator->fresh()->update(['is_active' => false]),
            fn () => $this->operator->fresh()->delete(),
        ] as $change) {
            try {
                $change();
                $this->fail('The last active Super Admin was changed.');
            } catch (LastSuperAdminException) {
            }
        }

        $this->assertTrue($this->operator->fresh()->isSuperAdmin());
        $this->assertTrue($this->operator->fresh()->is_active);
        $this->assertNotSoftDeleted($this->operator);
    }

    public function test_a_super_admin_can_be_demoted_or_deactivated_while_another_remains(): void
    {
        $other = User::create(['name' => 'Second', 'email' => 'second@platform.test', 'password' => 'password123', 'is_super_admin' => true, 'is_active' => true]);
        $this->actingAs($this->operator, 'admin');

        Livewire::test(EditUser::class, ['record' => $other->getKey()])
            ->fillForm(['is_super_admin' => false, 'is_active' => false, 'admin_modules' => [PlatformModules::PLANS]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($other->fresh()->isSuperAdmin());
        $this->assertFalse($other->fresh()->is_active);
    }

    public function test_deleting_restoring_and_permanently_deleting_an_account_is_logged(): void
    {
        $staff = $this->staff([PlatformModules::PLANS]);
        $this->actingAs($this->operator, 'admin');

        $staff->delete();
        $staff->restore();
        $staff->forceDelete();

        $lines = PlatformActivity::where('subject_type', 'User')->where('subject', 'Support')->orderBy('id')->get();

        $this->assertSame(['deleted', 'restored', 'deleted permanently'], $lines->where('action', '!=', 'created')->pluck('action')->values()->all());
        $this->assertTrue($lines->every(fn ($l) => $l->actor_id === $this->operator->id && $l->actor_type === 'Super admin'));
    }
}
