<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\ClientDatabase;
use App\Clients\ClientSetup;
use App\Filament\Pages\SearchListings;
use App\Filament\Pages\SeoSettings;
use App\Filament\Pages\ShopSoftwareSync;
use App\Filament\Pages\SiteSettings;
use App\Filament\Pages\StockLevels;
use App\Filament\Resources\ActivityLog\ActivityLogResource;
use App\Filament\Resources\ActivityLog\Pages\ListActivityLog;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Filament\Resources\PlatformActivities\PlatformActivityResource;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Support\ActivityTable;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlatformActivity;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storefront;
use App\Models\SyncAgent;
use App\Models\SyncToken;
use App\Models\User;
use App\Services\Inventory;
use App\Support\Activity\ActivityLog;
use App\Sync\AgentPackage;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

    public function test_a_changed_hsn_code_reads_with_its_label(): void
    {
        $this->enterClientPanel($this->admin);

        $this->coat->update(['hsn_code' => '6201']);

        $line = $this->lastLine();
        $this->assertSame('set the HSN code of product "Alpha Coat" to "6201"', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertSame(0, PlatformActivity::where('client_id', $this->beta->id)->count());
    }

    public function test_several_changes_are_listed_and_long_text_is_named_in_the_sentence_only(): void
    {
        $this->enterClientPanel($this->admin);
        $description = str_repeat('Soft merino wool, hand finished. ', 10);

        $this->coat->update(['name' => 'Alpha Jacket', 'price' => 800, 'description' => $description, 'is_new' => true]);

        $line = $this->lastLine();
        $this->assertStringStartsWith('updated product "Alpha Jacket": ', $line->description);
        $this->assertStringContainsString('price ₹1,000.00 → ₹800.00', $line->description);
        $this->assertStringContainsString('and 1 more', $line->description);
        $this->assertStringNotContainsString('merino', $line->description);
        $text = collect($line->changes)->firstWhere('field', 'description');
        $this->assertNull($text['old']);
        $this->assertNull($text['new']);

        // The details view shows the text itself, before and after.
        $row = collect(ActivityTable::rows($line))->firstWhere('label', 'Description');
        $this->assertSame('empty', $row['old']);
        $this->assertStringStartsWith('Soft merino wool, hand finished.', $row['new']);
    }

    public function test_a_rich_text_change_shows_its_words_before_and_after(): void
    {
        $this->coat->forceFill(['details' => '<p>Long wool coat.</p>'])->saveQuietly();
        $this->enterClientPanel($this->admin);

        $this->coat->update(['details' => '<p>Long wool coat. Full <strong>description</strong></p>']);

        $row = collect(ActivityTable::rows($this->lastLine()))->firstWhere('label', 'Details');
        $this->assertSame(['label' => 'Details', 'old' => 'Long wool coat.', 'new' => 'Long wool coat. Full description'], $row);
    }

    public function test_a_variants_change_shows_only_the_rows_that_changed(): void
    {
        $row = fn (string $size, int $stock, float $incl = 1050) => ['sku' => 'C-'.$size, 'price' => 1000, 'stock' => $stock, 'options' => ['Size' => $size], 'price_incl_tax' => $incl];
        $this->coat->forceFill(['variants' => [$row('S', 22), $row('M', 30), $row('L', 5)]])->saveQuietly();
        $this->enterClientPanel($this->admin);

        $this->coat->update(['variants' => [$row('S', 20), $row('M', 30), $row('XL', 4)]]);

        $line = $this->lastLine();
        $this->assertSame('changed the variants of product "Alpha Coat"', $line->description);
        $this->assertSame(
            ['label' => 'Variants', 'old' => 'S: stock 22; L', 'new' => 'S: stock 20; L removed; XL added'],
            collect(ActivityTable::rows($line))->firstWhere('label', 'Variants'),
        );

        // A variant's own HSN code reads by its label.
        $this->coat->update(['variants' => [$row('S', 20) + ['hsn_code' => '620530'], $row('M', 30), $row('XL', 4)]]);
        $this->assertSame(
            ['label' => 'Variants', 'old' => 'S: HSN code empty', 'new' => 'S: HSN code 620530'],
            collect(ActivityTable::rows($this->lastLine()))->firstWhere('label', 'Variants'),
        );
        $this->coat->update(['variants' => [$row('S', 20), $row('M', 30), $row('XL', 4)]]);

        // Only a worked-out value moved (the price with tax): no line of its own.
        $before = PlatformActivity::count();
        $this->coat->update(['variants' => [$row('S', 20, 1100), $row('M', 30, 1100), $row('XL', 4, 1100)]]);
        $this->assertSame($before, PlatformActivity::count());
    }

    public function test_only_columns_whose_value_really_changed_are_listed(): void
    {
        $this->enterClientPanel($this->admin);
        $this->coat->forceFill(['base_rating' => '0.0', 'variants' => ['b' => 2, 'a' => 1]])->saveQuietly();
        $before = PlatformActivity::count();

        // Same values written in another form: 0 for "0.0", the same JSON with its keys re-ordered.
        $this->coat->update(['base_rating' => 0, 'variants' => ['a' => 1, 'b' => 2]]);
        $this->assertSame($before, PlatformActivity::count());

        $this->coat->update(['base_rating' => 0, 'price' => 900]);
        $this->assertSame(['price'], array_column($this->lastLine()->changes, 'field'));
    }

    public function test_a_settings_save_keeps_before_and_after_of_the_changed_fields_only(): void
    {
        $this->enterClientPanel($this->admin);
        Livewire::test(SiteSettings::class)->set('data.site.name', 'Alpha Atelier')->call('save');
        $before = PlatformActivity::count();

        Livewire::test(SiteSettings::class)->call('save');
        $this->assertSame($before, PlatformActivity::count(), 'a save with nothing changed writes no line');

        Livewire::test(SiteSettings::class)->set('data.site.name', 'Alpha House')->call('save')->assertHasNoErrors();

        $line = $this->lastLine();
        $this->assertCount(1, $line->changes);
        $this->assertSame(['field' => 'site.name', 'old' => '"Alpha Atelier"', 'new' => '"Alpha House"'], array_intersect_key($line->changes[0], array_flip(['field', 'old', 'new'])));
        $this->assertSame([['label' => 'Site name', 'old' => 'Alpha Atelier', 'new' => 'Alpha House']], ActivityTable::rows($line));
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertSame(0, PlatformActivity::forClientStaff($this->beta->id)->count());
    }

    public function test_a_secret_setting_is_named_but_its_value_is_hidden(): void
    {
        $change = app(ActivityLog::class)->describer()->settingChange('whatsapp.api_key', 'API key', 'old-key', 'new-key');

        $this->assertNull($change['old']);
        $this->assertNull($change['new']);
        $line = new PlatformActivity(['changes' => [$change]]);
        $this->assertSame([['label' => 'API key', 'old' => 'hidden', 'new' => 'hidden']], ActivityTable::rows($line));
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

    public function test_marking_products_new_or_best_seller_is_logged_one_line_per_bulk_action(): void
    {
        $scarf = $this->product('Alpha Scarf', 300);
        $shawl = $this->product('Alpha Shawl', 700);
        $shawl->update(['is_new' => true]); // already new: left alone
        $this->enterClientPanel($this->admin);
        $before = PlatformActivity::count();

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('markNew', [$this->coat, $scarf, $shawl])
            ->assertHasNoErrors();

        $this->assertSame([true, true, true], [$this->coat->fresh()->is_new, $scarf->fresh()->is_new, $shawl->fresh()->is_new]);
        $this->assertSame($before + 1, PlatformActivity::count());
        $line = $this->lastLine();
        $this->assertSame('updated', $line->action);
        $this->assertStringContainsString('2 products', $line->description);
        $this->assertStringContainsString('Alpha Scarf', $line->description);
        $this->assertStringNotContainsString('Alpha Shawl', $line->description);
        $this->assertSame(['Products', $this->alpha->id], [$line->area, $line->client_id]);
        $this->assertSame(0, PlatformActivity::forClientStaff($this->beta->id)->count());

        // One product switched in its row: the usual line.
        $this->coat->update(['is_best_seller' => true]);
        $this->assertStringContainsString('best seller', $this->lastLine()->description);
    }

    public function test_hiding_products_from_the_storefront_in_bulk_is_one_line(): void
    {
        $scarf = $this->product('Alpha Scarf', 300);
        $this->enterClientPanel($this->admin);
        $before = PlatformActivity::count();

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('hideFromStorefront', [$this->coat, $scarf])
            ->assertHasNoErrors();

        $this->assertSame([false, false], [$this->coat->fresh()->is_active, $scarf->fresh()->is_active]);
        $this->assertSame($before + 1, PlatformActivity::count());
        $this->assertStringContainsString('2 products', $this->lastLine()->description);
        $this->assertSame($this->alpha->id, $this->lastLine()->client_id);

        Livewire::test(ListProducts::class)->callTableBulkAction('showOnStorefront', [$this->coat]);
        $this->assertTrue($this->coat->fresh()->is_active);
        $this->assertFalse($scarf->fresh()->is_active);
    }

    public function test_marking_needs_permission_to_edit_products(): void
    {
        $role = ClientRole::create(['client_id' => $this->alpha->id, 'name' => 'Viewer', 'permissions' => ['products.view']]);
        $viewer = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Vik Viewer', 'email' => 'vik@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'client_role_id' => $role->id, 'is_active' => true,
        ]);
        $this->enterClientPanel($viewer);

        Livewire::test(ListProducts::class)
            ->assertTableBulkActionHidden('markNew')
            ->assertTableBulkActionHidden('markBestSeller')
            ->assertTableBulkActionHidden('hideFromStorefront');
        $this->assertFalse($this->coat->fresh()->is_new);
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

    public function test_editing_a_search_listing_is_logged_against_the_record(): void
    {
        $this->enterClientPanel($this->admin);

        Livewire::test(SearchListings::class)
            ->callAction(TestAction::make('editSeo')->table($this->coat), ['meta_title' => 'Wool coats for winter', 'noindex' => true])
            ->assertHasNoFormErrors();

        $line = $this->lastLine();
        $this->assertSame('updated', $line->action);
        $this->assertStringContainsString('product "Alpha Coat"', $line->description);
        $this->assertStringContainsString('SEO title', $line->description);
        $this->assertSame($this->coat->id, $line->subject_id);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertSame(0, PlatformActivity::forClientStaff($this->beta->id)->count());
    }

    public function test_hiding_listings_in_bulk_is_one_line(): void
    {
        $scarf = $this->product('Alpha Scarf', 300);
        $this->enterClientPanel($this->admin);
        $before = PlatformActivity::count();

        Livewire::test(SearchListings::class)
            ->callTableBulkAction('hideFromSearch', [$this->coat, $scarf])
            ->assertHasNoErrors();

        $this->assertSame($before + 1, PlatformActivity::count());
        $line = $this->lastLine();
        $this->assertStringContainsString('Alpha Coat', $line->description);
        $this->assertStringContainsString('Alpha Scarf', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertTrue($scarf->fresh()->noindex);
    }

    public function test_saving_seo_settings_is_logged(): void
    {
        $this->enterClientPanel($this->admin);

        Livewire::test(SeoSettings::class)
            ->set('data.seo.default_title', 'Alpha Atelier — Coats')
            ->call('save')
            ->assertHasNoErrors();

        $line = $this->lastLine();
        $this->assertSame('updated', $line->action);
        $this->assertSame('Settings', $line->area);
        $this->assertStringContainsString('SEO settings', $line->description);
        $this->assertStringContainsString('homepage title', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertSame(0, PlatformActivity::forClientStaff($this->beta->id)->count());
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

    public function test_a_stock_adjustment_is_one_line_in_inventory(): void
    {
        $this->enterClientPanel($this->admin);
        $before = PlatformActivity::count();

        Livewire::test(StockLevels::class)
            ->callAction(TestAction::make('adjust')->table($this->coat), ['type' => StockMovement::RECEIVED, 'quantity' => 15, 'note' => 'Invoice 553'])
            ->assertHasNoFormErrors();

        // One line: the stock movement itself adds none, and the product's own save is quiet.
        $this->assertSame($before + 1, PlatformActivity::count());
        $line = $this->lastLine();
        $this->assertSame('updated', $line->action);
        $this->assertSame('received 15 units of product "Alpha Coat", stock 10 → 25', $line->description);
        $this->assertSame('Inventory', $line->area);
        $this->assertSame($this->coat->id, $line->subject_id);
        $this->assertSame([['field' => 'stock', 'label' => 'stock', 'old' => '10', 'new' => '25']], $line->changes);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertSame(0, PlatformActivity::forClientStaff($this->beta->id)->count());

        app(Inventory::class)->adjust($this->coat, null, StockMovement::COUNT, 25);
        $this->assertSame('counted the stock of product "Alpha Coat", stock stays 25', $this->lastLine()->description);
    }

    public function test_changing_the_low_stock_level_is_logged(): void
    {
        $this->enterClientPanel($this->admin);

        Livewire::test(StockLevels::class)->callAction('lowStockLevel', ['level' => 3]);

        $line = $this->lastLine();
        $this->assertSame('updated', $line->action);
        $this->assertSame('changed the low-stock level from 5 to 3', $line->description);
        $this->assertSame(['Inventory', 'Settings', $this->alpha->id], [$line->area, $line->subject_type, $line->client_id]);
        $this->assertSame(0, PlatformActivity::forClientStaff($this->beta->id)->count());
    }

    public function test_changing_the_other_categories_of_a_product_is_logged(): void
    {
        $category = fn (string $name) => Category::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true]);
        $coats = $category('Coats');
        $gifts = $category('Gifts');
        $this->coat->update(['category_id' => $coats->id]);
        $this->enterClientPanel($this->admin);

        $this->coat->listUnder([$gifts->id]);

        $line = $this->lastLine();
        $this->assertSame('updated', $line->action);
        $this->assertSame('changed the other categories of product "Alpha Coat" from none to Gifts', $line->description);
        $this->assertSame([$this->coat->id, $this->alpha->id], [$line->subject_id, $line->client_id]);
        $this->assertSame(0, PlatformActivity::forClientStaff($this->beta->id)->count());

        // The main category is always kept, and a save with nothing changed adds no line.
        $before = PlatformActivity::count();
        $this->coat->listUnder([$gifts->id]);
        $this->assertSame($before, PlatformActivity::count());
        $this->assertEqualsCanonicalizing([$coats->id, $gifts->id], $this->coat->categories()->pluck('categories.id')->all());
    }

    public function test_taking_a_category_out_of_the_menu_and_homepage_reads_well(): void
    {
        $men = Category::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Men', 'slug' => 'men', 'is_active' => true, 'show_in_menu' => true]);
        $this->enterClientPanel($this->admin);

        $men->update(['show_in_menu' => false]);

        $line = $this->lastLine();
        $this->assertSame('updated', $line->action);
        $this->assertSame('turned off "feature in menu & homepage" for category "Men"', $line->description);
        $this->assertSame([$men->id, $this->alpha->id], [$line->subject_id, $line->client_id]);
        $this->assertSame(0, PlatformActivity::forClientStaff($this->beta->id)->count());
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

    public function test_the_details_open_on_their_own_page_with_each_field_before_and_after(): void
    {
        $this->enterClientPanel($this->admin);
        $this->coat->update(['price' => 1200]);
        $line = $this->lastLine();

        Livewire::test(ListActivityLog::class)
            ->assertCanSeeTableRecords([$line])
            ->assertActionHasUrl(TestAction::make('view')->table($line), ActivityLogResource::getUrl('view', ['record' => $line]));

        $this->get('/client/activity-log/'.$line->id)->assertSuccessful()
            ->assertSee('Activity details')
            ->assertSee('Back to activity log')
            ->assertSee('What changed')
            ->assertSee('₹1,000.00')
            ->assertSee('₹1,200.00');
    }

    public function test_another_clients_activity_page_is_not_found(): void
    {
        $this->seedLines();
        $theirs = PlatformActivity::where('client_id', $this->beta->id)->firstOrFail();
        $this->enterClientPanel($this->admin);

        $this->get('/client/activity-log/'.$theirs->id)->assertNotFound();
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

    public function test_record_pages_do_not_show_activity_only_the_activity_log_does(): void
    {
        $order = Order::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'number' => 'ME-2001',
            'email' => 'shopper@alpha.test', 'status' => 'confirmed',
            'shipping_name' => 'Shopper', 'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai',
            'shipping_state' => 'MH', 'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'subtotal' => 500, 'total' => 500,
        ]);
        $this->enterClientPanel($this->admin);
        $this->get('/client/orders/'.$order->id)->assertSuccessful()->assertDontSee('No activity yet');
        $this->get('/client/products/'.$this->coat->id)->assertSuccessful()->assertDontSee('No activity yet');

        $this->flushSession();
        $operator = User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]);
        $this->actingAs($operator, 'admin');
        $this->get('/clients/'.$this->alpha->id)->assertSuccessful()->assertDontSee('No activity yet');
        $this->get(PlatformActivityResource::getUrl('index'))->assertSuccessful();

        $line = PlatformActivity::latest('id')->firstOrFail();
        $this->get(PlatformActivityResource::getUrl('view', ['record' => $line]))->assertSuccessful()
            ->assertSee('Activity details');
    }

    public function test_moving_a_clients_database_to_a_new_name_is_logged_for_that_client(): void
    {
        config(['clients.isolation' => 'database', 'clients.prefix' => 'client_logtest_'.getmypid().'_']);
        $databases = app(ClientDatabase::class);
        $from = $this->alpha->databaseName();
        $to = 'client_logtest_'.getmypid().'_alpha_moved';
        $this->artisan('clients:provision', ['subdomain' => 'alpha', '--no-seed' => true])->assertSuccessful();

        try {
            $this->actingAs(User::create([
                'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
                'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
            ]), 'admin');
            Filament::setCurrentPanel(Filament::getPanel('admin'));

            $this->alpha->update(['database' => $to]);

            $line = PlatformActivity::where('description', 'like', 'moved the database%')->sole();
            $this->assertSame('updated', $line->action);
            $this->assertSame('moved the database of client "Alpha" from '.$from.' to '.$to, $line->description);
            $this->assertSame($this->alpha->id, $line->client_id);
            $this->assertSame([['field' => 'database', 'label' => 'database', 'old' => $from, 'new' => $to]], $line->changes);
            $this->assertSame(0, PlatformActivity::where('client_id', $this->beta->id)->where('description', 'like', 'moved the database%')->count());
        } finally {
            DB::purge($databases->connectionNameFor($this->alpha));
            File::delete([database_path($from.'.sqlite'), database_path($to.'.sqlite')]);
        }
    }

    public function test_editing_a_clients_business_details_is_logged_for_that_client(): void
    {
        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditClient::class, ['record' => $this->alpha->getRouteKey()])
            ->set('data.business.business_name', 'Alpha Retail Pvt Ltd')
            ->set('data.business.gstin', '03AAACA1234A1Z5')
            ->call('save')
            ->assertHasNoFormErrors();

        $line = PlatformActivity::where('description', 'like', '%business details%')->sole();
        $this->assertSame('updated', $line->action);
        // The PAN and state came from the GSTIN.
        $this->assertSame('updated the business details of client "Alpha": registered business name, GSTIN, PAN and state the business sells from', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertSame(0, PlatformActivity::where('client_id', $this->beta->id)->where('description', 'like', '%business details%')->count());
    }

    public function test_the_business_details_of_a_new_client_are_logged_for_that_client(): void
    {
        $this->mock(ClientSetup::class, fn ($mock) => $mock->shouldReceive('startInBackground'));
        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateClient::class)
            ->fillForm([
                'business' => ['business_name' => 'Delta Traders', 'gstin' => '03AAACA1234A1Z5', 'pan' => 'AAACA1234A'],
                'name' => 'Delta', 'subdomain' => 'delta', 'storefront_id' => $this->alpha->storefront_id,
                'admin' => ['name' => 'Delta Owner', 'email' => 'owner@delta.test', 'password' => 'secret-pass1'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $delta = Client::where('subdomain', 'delta')->sole();

        $line = PlatformActivity::where('description', 'like', 'added the business details%')->sole();
        $this->assertSame('updated', $line->action);
        $this->assertSame('added the business details of client "Delta"', $line->description);
        $this->assertSame($delta->id, $line->client_id);
        // The state came from the GSTIN.
        $this->assertSame(['registered business name', 'GSTIN', 'PAN', 'state the business sells from'], array_column($line->changes, 'label'));
        $this->assertSame('"03AAACA1234A1Z5"', $line->changes[1]['new']);
        $this->assertSame(0, PlatformActivity::whereIn('client_id', [$this->alpha->id, $this->beta->id])->where('description', 'like', 'added the business details%')->count());
    }

    private function enterAdminPanel(): void
    {
        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_creating_a_sync_agent_setup_code_is_logged_for_that_client(): void
    {
        config(['app.url' => 'https://platform.test']);
        $this->mock(AgentPackage::class, fn ($mock) => $mock->shouldReceive('isSigned')->andReturn(true));
        $this->enterAdminPanel();

        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])
            ->callAction('syncSetupCode')
            ->assertActionMounted('showSetupCode');

        $this->assertSame(1, SyncToken::active()->where('client_id', $this->alpha->id)->count());
        $line = $this->lastLine();
        $this->assertSame('created', $line->action);
        $this->assertSame('created a shop software setup code for client "Alpha"', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertNull($line->changes); // the code itself is never kept
    }

    public function test_setting_the_billing_software_connection_is_logged_and_the_key_is_never_kept(): void
    {
        $this->enterAdminPanel();

        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])
            ->callAction('syncConnection', ['url' => 'http://localhost:9000/api', 'key' => 'secret-key-123'])
            ->assertHasNoFormErrors();

        $line = $this->lastLine();
        $this->assertSame('updated', $line->action);
        $this->assertSame('changed the billing software connection of client "Alpha": API address and API key', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertSame('http://localhost:9000/api', $line->changes[0]['new']);
        $this->assertStringNotContainsString('secret-key-123', json_encode($line->changes));
        $this->assertSame('hidden', ActivityTable::rows($line)[1]['new']);

        // The key is required until one is saved; then a blank keeps it and nothing is logged.
        Livewire::test(ViewClient::class, ['record' => $this->beta->getRouteKey()])
            ->callAction('syncConnection', ['url' => 'http://localhost:8765/ecom/v1', 'key' => ''])
            ->assertHasFormErrors(['key' => 'required']);
        $count = PlatformActivity::count();
        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])
            ->callAction('syncConnection', ['url' => 'http://localhost:9000/api', 'key' => ''])
            ->assertHasNoFormErrors();
        $this->assertSame($count, PlatformActivity::count());
    }

    public function test_revoking_sync_access_is_logged_and_leaves_other_clients_connected(): void
    {
        SyncToken::issue($this->alpha, 'agent');
        SyncToken::issue($this->beta, 'agent');
        $this->enterAdminPanel();

        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])->callAction('revokeSync');

        $this->assertSame(0, SyncToken::active()->where('client_id', $this->alpha->id)->count());
        $this->assertSame(1, SyncToken::active()->where('client_id', $this->beta->id)->count());
        $line = $this->lastLine();
        $this->assertSame('revoked', $line->action);
        $this->assertSame('revoked the shop software sync access of client "Alpha"', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);
        $this->assertSame('Revoked', ActivityTable::ACTIONS['revoked']);
    }

    public function test_downloading_the_windows_7_installer_is_logged_for_that_client(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'setup');
        file_put_contents($file, 'installer');
        $this->beforeApplicationDestroyed(fn () => @unlink($file));
        $this->mock(AgentPackage::class, fn ($mock) => $mock->shouldReceive('isSigned')->with('amd64-win7')->andReturn(true)
            ->shouldReceive('hasInstaller')->with('amd64-win7')->andReturn(true)
            ->shouldReceive('installerPath')->with('amd64-win7')->andReturn($file));
        $this->enterAdminPanel();

        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])
            ->assertSee('Installer for 64-bit Windows 7 / 8.1')
            ->callAction('downloadAgentamd64win7')
            ->assertFileDownloaded(basename($file));

        $this->assertSame('downloaded the sync agent installer for 64-bit Windows 7 / 8.1 for client "Alpha"', $this->lastLine()->description);
        $this->assertSame($this->alpha->id, $this->lastLine()->client_id);
    }

    public function test_downloading_the_sync_agent_installer_is_logged_for_that_client(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'setup');
        file_put_contents($file, 'installer');
        $this->beforeApplicationDestroyed(fn () => @unlink($file));
        $this->mock(AgentPackage::class, fn ($mock) => $mock->shouldReceive('isSigned')->andReturn(true)
            ->shouldReceive('hasInstaller')->with('386')->andReturn(true)
            ->shouldReceive('installerPath')->with('386')->andReturn($file));
        $this->enterAdminPanel();

        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])
            ->assertSee('Installer for 32-bit Windows 10 / 11')
            ->callAction('downloadAgent386')
            ->assertFileDownloaded(basename($file));

        $line = $this->lastLine();
        $this->assertSame('downloaded', $line->action);
        $this->assertSame('downloaded the sync agent installer for 32-bit Windows 10 / 11 for client "Alpha"', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);
    }

    public function test_an_installer_that_is_not_built_is_not_downloaded(): void
    {
        $this->mock(AgentPackage::class, fn ($mock) => $mock->shouldReceive('isSigned')->andReturn(true)
            ->shouldReceive('hasInstaller')->andReturn(false));
        $this->enterAdminPanel();

        Livewire::test(ViewClient::class, ['record' => $this->alpha->getRouteKey()])
            ->callAction('downloadAgentamd64')
            ->assertNoFileDownloaded()
            ->assertNotified('The installer can\'t be downloaded');

        $this->assertSame(0, PlatformActivity::where('action', 'downloaded')->count());
    }

    public function test_a_client_admin_connects_their_own_shop_from_their_panel_and_it_is_logged(): void
    {
        config(['app.url' => 'https://platform.test']);
        $file = tempnam(sys_get_temp_dir(), 'agent');
        file_put_contents($file, 'binary');
        $this->beforeApplicationDestroyed(fn () => @unlink($file));
        $this->mock(AgentPackage::class, fn ($mock) => $mock->shouldReceive('isSigned')->andReturn(true)
            ->shouldReceive('hasInstaller')->andReturn(true)
            ->shouldReceive('installerPath')->andReturn($file));

        // Other staff never see the page: a setup code is a password to the store's data.
        $this->enterClientPanel($this->staff);
        $this->assertFalse(ShopSoftwareSync::canAccess());

        $this->enterClientPanel($this->admin);
        // Both installers are offered in the download box, which marks the one this computer needs.
        Livewire::test(ShopSoftwareSync::class)
            ->assertSee('Installer for 64-bit Windows 10 / 11')
            ->assertSee('Installer for 32-bit Windows 10 / 11')
            ->callAction('downloadAgentamd64')
            ->assertFileDownloaded(basename($file));
        $this->assertSame('downloaded the sync agent installer for 64-bit Windows 10 / 11 for client "Alpha"', $this->lastLine()->description);
        $this->assertSame($this->alpha->id, $this->lastLine()->client_id);

        Livewire::test(ShopSoftwareSync::class)
            ->assertSee('Connect your billing software')
            ->assertSee('Not set up')
            ->callAction('syncSetupCode')
            ->assertActionMounted('showSetupCode');

        $this->assertSame(1, SyncToken::active()->where('client_id', $this->alpha->id)->count());
        $this->assertSame(0, SyncToken::where('client_id', $this->beta->id)->count());
        $line = $this->lastLine();
        $this->assertSame('Priya Sharma', $line->actor);
        $this->assertSame('created', $line->action);
        $this->assertSame('created a shop software setup code for client "Alpha"', $line->description);
        $this->assertSame($this->alpha->id, $line->client_id);

        Livewire::test(ShopSoftwareSync::class)->callAction('revokeSync');
        $this->assertSame(0, SyncToken::active()->where('client_id', $this->alpha->id)->count());
        $this->assertSame('revoked the shop software sync access of client "Alpha"', $this->lastLine()->description);
        $this->assertSame($this->alpha->id, $this->lastLine()->client_id);
    }

    public function test_the_clients_list_shows_each_clients_sync_status(): void
    {
        SyncToken::issue($this->alpha, 'agent');
        SyncAgent::forClient($this->alpha)->update(['last_seen_at' => now(), 'last_report' => ['ok' => true, 'message' => 'Sync complete.']]);
        $this->enterAdminPanel();

        Livewire::test(ListClients::class)
            ->assertTableColumnStateSet('sync_status', 'Syncing', $this->alpha)
            ->assertTableColumnStateSet('sync_status', 'Not set up', $this->beta);
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
