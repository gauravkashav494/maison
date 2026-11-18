<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Pages\SiteSettings;
use App\Filament\RelationManagers\HistoryRelationManager;
use App\Filament\Resources\ActivityLog\Pages\ListActivityLog;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Support\ActivityTable;
use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlatformActivity;
use App\Models\Product;
use App\Models\Storefront;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Everything a client's staff do in their panel is in the activity log, as a sentence anyone
 * can read — and only the right people see it: a Client Admin the whole team, other staff
 * their own lines, never another client's.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private Client $beta;

    private ClientUser $admin;

    private ClientUser $staff;

    private Product $coat;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->admin = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Priya Sharma', 'email' => 'priya@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $role = ClientRole::create(['client_id' => $this->alpha->id, 'name' => 'Catalogue editor', 'permissions' => ['products.view', 'products.update']]);
        $this->staff = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Rahul Verma', 'email' => 'rahul@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'client_role_id' => $role->id, 'is_active' => true,
        ]);

        $this->coat = $this->product('Alpha Coat', 1000);
    }

    private function product(string $name, float $price, ?Client $client = null): Product
    {
        return Product::withoutGlobalScopes()->create([
            'client_id' => ($client ?? $this->alpha)->id, 'template' => 'fashion',
            'name' => $name, 'slug' => str($name)->slug(), 'price' => $price, 'stock' => 10, 'is_active' => true,
        ]);
    }

    private function enterClientPanel(ClientUser $user): void
    {
        $this->actingAs($user, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($user);
        app(StoreContext::class)->resolve($user, app(ClientContext::class)->templateId());
    }

    private function lastLine(): PlatformActivity
    {
        return PlatformActivity::latest('id')->firstOrFail();
    }

    // ---- Sentences --------------------------------------------------------------------------

    public function test_a_change_reads_as_one_sentence_with_before_and_after(): void
    {
        $this->enterClientPanel($this->admin);

        $this->coat->update(['price' => 899.5]);

        $line = $this->lastLine();
        $this->assertSame('changed the price of product "Alpha Coat" from ₹1,000.00 to ₹899.50', $line->description);
        $this->assertSame('Priya Sharma', $line->actor);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertSame($this->coat->id, $line->subject_id);
        $this->assertSame('Products', $line->area);
        $this->assertSame([['field' => 'price', 'label' => 'price', 'old' => '₹1,000.00', 'new' => '₹899.50']], $line->changes);
    }

    public function test_several_changes_are_listed_and_long_text_is_named_but_not_copied(): void
    {
        $this->enterClientPanel($this->admin);
        $description = str_repeat('Soft merino wool, hand finished. ', 10);

        $this->coat->update(['name' => 'Alpha Jacket', 'price' => 800, 'description' => $description, 'is_new' => true]);

        $line = $this->lastLine();
        $this->assertStringStartsWith('updated product "Alpha Jacket": ', $line->description);
        $this->assertStringContainsString('price ₹1,000.00 → ₹800.00', $line->description);
        $this->assertStringContainsString('and 1 more', $line->description);
        $this->assertStringNotContainsString('merino', json_encode($line->getAttributes()));
        $text = collect($line->changes)->firstWhere('field', 'description');
        $this->assertNull($text['old']);
        $this->assertNull($text['new']);
    }

    public function test_switching_a_record_on_or_off_says_so(): void
    {
        $this->enterClientPanel($this->admin);

        $this->coat->update(['is_active' => false]);
        $this->assertSame('switched off product "Alpha Coat"', $this->lastLine()->description);

        $this->coat->update(['is_active' => true]);
        $this->assertSame('switched on product "Alpha Coat"', $this->lastLine()->description);
    }

    public function test_secrets_are_named_and_never_stored(): void
    {
        $this->enterClientPanel($this->admin);

        $this->staff->update(['password' => 'a-brand-new-secret']);
        $line = $this->lastLine();
        $this->assertSame('changed the password of staff account "Rahul Verma"', $line->description);
        $this->assertStringNotContainsString('a-brand-new-secret', json_encode($line->getAttributes()));
        $this->assertSame('Team', $line->area);

        $this->admin->update(['password' => 'my-own-new-secret']);
        $this->assertSame('changed their password', $this->lastLine()->description);
    }

    public function test_an_order_status_move_reads_as_what_happened_to_the_order(): void
    {
        $order = Order::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'number' => 'ME-1043',
            'email' => 'shopper@alpha.test', 'status' => 'confirmed',
            'shipping_name' => 'Shopper', 'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai',
            'shipping_state' => 'MH', 'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'subtotal' => 500, 'total' => 500,
        ]);
        $this->enterClientPanel($this->admin);

        $order->transitionTo(Order::STATUS_DISPATCHED, 'Sent with BlueDart', $this->admin);

        $line = $this->lastLine();
        $this->assertSame('marked order ME-1043 as Dispatched', $line->description);
        $this->assertSame('Orders', $line->area);
    }

    public function test_trash_restore_and_permanent_delete_each_have_their_own_words(): void
    {
        $this->enterClientPanel($this->admin);

        $this->coat->delete();
        $this->coat->restore();
        $this->coat->forceDelete();

        // The product itself was created in setUp, before anyone signed in, so it has no "added" line.
        $this->assertSame([
            'moved product "Alpha Coat" to the trash',
            'restored product "Alpha Coat" from the trash',
            'permanently deleted product "Alpha Coat"',
        ], PlatformActivity::orderBy('id')->pluck('description')->all());
    }

    // ---- Who is logged ----------------------------------------------------------------------

    public function test_shoppers_and_console_work_are_never_logged_as_staff(): void
    {
        // No one signed in: seeders, imports, scheduled jobs.
        $this->coat->update(['stock' => 9]);

        // A storefront request, even in a browser where staff are also signed in.
        $this->actingAs($this->admin, 'client');
        app(ClientContext::class)->resolveForPublic($this->alpha);
        $this->coat->update(['stock' => 8]);

        $this->assertSame(0, PlatformActivity::count());
    }

    public function test_a_bulk_action_is_one_line_listing_the_records(): void
    {
        $scarf = $this->product('Alpha Scarf', 300);
        $shawl = $this->product('Alpha Shawl', 700);
        $this->enterClientPanel($this->admin);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('delete', [$this->coat, $scarf, $shawl])
            ->assertHasNoErrors();

        $lines = PlatformActivity::where('action', 'deleted')->get();
        $this->assertCount(1, $lines);
        $this->assertStringStartsWith('moved 3 products to the trash: ', $lines[0]->description);
        $this->assertStringContainsString('Alpha Scarf', $lines[0]->description);
        $this->assertCount(3, $lines[0]->changes);
        $this->assertSame($this->alpha->id, $lines[0]->client_id);
    }

    public function test_saving_settings_names_the_fields_by_their_labels(): void
    {
        $this->enterClientPanel($this->admin);

        Livewire::test(SiteSettings::class)
            ->set('data.site.name', 'Alpha Atelier')
            ->call('save')
            ->assertHasNoErrors();

        $line = $this->lastLine();
        $this->assertSame('Settings', $line->area);
        $this->assertStringStartsWith('updated ', $line->description);
        $this->assertStringContainsString('site name', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);
    }

    public function test_sign_ins_and_failed_attempts_are_logged_for_the_client(): void
    {
        event(new Login('client', $this->staff, false));
        event(new Failed('client', $this->staff, ['email' => $this->staff->email]));

        $lines = PlatformActivity::orderBy('id')->get();
        $this->assertSame(['signed in', 'sign-in failed'], $lines->pluck('action')->all());
        $this->assertTrue($lines->every(fn ($l) => $l->client_id === $this->alpha->id && $l->area === 'Sign-in' && $l->actor === 'Rahul Verma'));
    }

    // ---- Who sees what ----------------------------------------------------------------------

    private function seedLines(): void
    {
        $this->enterClientPanel($this->admin);
        $this->coat->update(['name' => 'Renamed By Priya']);

        $this->flushSession();
        $this->enterClientPanel($this->staff);
        $this->product('Alpha Scarf', 300)->update(['name' => 'Renamed By Rahul']);

        $betaAdmin = ClientUser::create([
            'client_id' => $this->beta->id, 'name' => 'Beta Admin', 'email' => 'admin@beta.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->flushSession();
        $this->enterClientPanel($betaAdmin);
        $this->product('Beta Coat', 500, $this->beta)->update(['name' => 'Renamed By Beta']);
        $this->flushSession();
    }

    public function test_a_client_admin_sees_the_whole_team_and_never_another_client(): void
    {
        $this->seedLines();
        $this->enterClientPanel($this->admin);

        $this->get('/client/activity-log')->assertSuccessful()
            ->assertSee('Activity log')
            ->assertSee('Renamed By Priya')
            ->assertSee('Renamed By Rahul')
            ->assertSee('Rahul Verma')
            ->assertDontSee('Renamed By Beta');
    }

    public function test_the_details_show_each_field_before_and_after(): void
    {
        $this->enterClientPanel($this->admin);
        $this->coat->update(['price' => 1200]);
        $line = $this->lastLine();

        // The modal mounts (Filament sends its body as a separate partial, so it is not in html()).
        Livewire::test(ListActivityLog::class)
            ->assertCanSeeTableRecords([$line])
            ->mountAction(TestAction::make('view')->table($line))
            ->assertActionMounted(TestAction::make('view')->table($line))
            ->assertHasNoErrors();

        $this->assertSame(
            [['label' => 'Price', 'old' => '₹1,000.00', 'new' => '₹1,200.00']],
            ActivityTable::rows($line),
        );
    }

    public function test_other_staff_see_only_their_own_activity(): void
    {
        $this->seedLines();
        $this->enterClientPanel($this->staff);

        $this->get('/client/activity-log')->assertSuccessful()
            ->assertSee('My activity')
            ->assertSee('Renamed By Rahul')
            ->assertDontSee('Renamed By Priya')
            ->assertDontSee('Renamed By Beta');
    }

    public function test_a_record_shows_its_own_history(): void
    {
        $this->seedLines();
        $this->enterClientPanel($this->admin);
        $coat = $this->coat->fresh();

        Livewire::test(HistoryRelationManager::class, ['ownerRecord' => $coat, 'pageClass' => EditProduct::class])
            ->assertCanSeeTableRecords(PlatformActivity::where('subject_type', 'Product')->where('subject_id', $coat->id)->get())
            ->assertCanNotSeeTableRecords(PlatformActivity::where('subject', 'like', 'Renamed By R%')->orWhere('subject', 'like', 'Renamed By B%')->get());
    }

    public function test_history_and_activity_tabs_render(): void
    {
        $order = Order::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'number' => 'ME-2001',
            'email' => 'shopper@alpha.test', 'status' => 'confirmed',
            'shipping_name' => 'Shopper', 'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai',
            'shipping_state' => 'MH', 'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'subtotal' => 500, 'total' => 500,
        ]);
        $this->enterClientPanel($this->admin);
        $this->get('/client/orders/'.$order->id)->assertSuccessful()->assertSee('History');

        $this->flushSession();
        $operator = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
        $this->actingAs($operator, 'admin');
        $this->get('/clients/'.$this->alpha->id)->assertSuccessful()->assertSee('Activity');
    }

    public function test_lines_older_than_a_year_are_pruned(): void
    {
        $base = ['actor' => 'Priya Sharma', 'actor_type' => 'Client staff', 'action' => 'updated', 'subject_type' => 'Product', 'subject' => 'Alpha Coat', 'client_id' => $this->alpha->id];
        PlatformActivity::create($base + ['created_at' => now()->subMonths(13)]);
        $kept = PlatformActivity::create($base + ['created_at' => now()->subMonths(11)]);

        $this->artisan('model:prune', ['--model' => [PlatformActivity::class]])->assertSuccessful();

        $this->assertSame([$kept->id], PlatformActivity::pluck('id')->all());
    }
}
