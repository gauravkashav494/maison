<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Pages\SearchListings;
use App\Filament\Pages\SeoSettings;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SEO is its own module: the search & social fields are edited in SEO → Search listings (one
 * content type at a time) and the site-wide defaults in SEO → SEO settings, never on the
 * content forms or in Site settings. Both need the "SEO" permission.
 */
class SeoModuleTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private ClientUser $admin;

    private Product $coat;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->admin = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Priya Sharma', 'email' => 'priya@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->coat = Product::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion',
            'name' => 'Alpha Coat', 'slug' => 'alpha-coat', 'price' => 1000, 'is_active' => true,
            'description' => 'A warm wool coat.',
        ]);
    }

    protected function tearDown(): void
    {
        Cache::forget('settings.all');
        parent::tearDown();
    }

    private function staff(array $permissions): ClientUser
    {
        $role = ClientRole::create(['client_id' => $this->alpha->id, 'name' => 'Role '.uniqid(), 'permissions' => $permissions]);

        return ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Rahul Verma', 'email' => uniqid().'@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'client_role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function enterClientPanel(ClientUser $user): void
    {
        $this->actingAs($user, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($user);
        app(StoreContext::class)->resolve($user, app(ClientContext::class)->templateId());
    }

    // ---- Forms ------------------------------------------------------------------------------

    public function test_content_forms_no_longer_carry_the_seo_fields(): void
    {
        $this->enterClientPanel($this->admin);
        $category = Category::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Coats', 'slug' => 'coats']);

        Livewire::test(EditProduct::class, ['record' => $this->coat->getRouteKey()])
            ->assertFormFieldDoesNotExist('meta_title')
            ->assertFormFieldDoesNotExist('noindex');
        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->assertFormFieldDoesNotExist('meta_description');
        Livewire::test(SiteSettings::class)
            ->assertFormFieldDoesNotExist('seo.default_title');
    }

    // ---- Who can open it --------------------------------------------------------------------

    public function test_the_seo_pages_need_the_seo_permission(): void
    {
        $this->actingAs($this->staff(['products.view', 'products.update']), 'client');
        $this->get('/client/seo')->assertForbidden();
        $this->get('/client/seo-settings')->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->staff(['seo.view']), 'client');
        $this->get('/client/seo')->assertSuccessful();
        $this->get('/client/seo-settings')->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->staff(['seo.view', 'seo.update']), 'client');
        $this->get('/client/seo-settings')->assertSuccessful();
    }

    public function test_a_view_only_role_cannot_edit_a_listing(): void
    {
        $this->enterClientPanel($this->staff(['seo.view']));

        Livewire::test(SearchListings::class)
            ->assertCanSeeTableRecords([$this->coat])
            ->assertActionHidden(TestAction::make('editSeo')->table($this->coat));
    }

    public function test_a_switched_off_module_drops_out_and_the_store_kind_decides_the_types(): void
    {
        $this->enterClientPanel($this->admin);
        $types = array_keys(SearchListings::types());
        $this->assertContains('products', $types);
        $this->assertContains('pages', $types);
        $this->assertNotContains('services', $types, 'a fashion store has no services');

        $this->alpha->update(['modules' => ['orders', 'customers', 'products', 'categories', 'taxes', 'appearance', 'seo']]);
        $this->enterClientPanel($this->admin->fresh());
        $this->assertNotContains('pages', array_keys(SearchListings::types()));

        $this->alpha->update(['modules' => ['orders', 'customers', 'products', 'categories', 'taxes', 'appearance']]);
        $this->flushSession();
        $this->actingAs($this->admin->fresh(), 'client');
        $this->get('/client/seo')->assertForbidden();
    }

    // ---- Listings ---------------------------------------------------------------------------

    public function test_listings_show_the_defaults_and_switch_content_type(): void
    {
        $brand = Brand::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Jaquar', 'slug' => 'jaquar']);
        $page = Page::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'storefront_template' => 'fashion', 'title' => 'About us', 'slug' => 'about', 'template' => 'default']);
        $this->enterClientPanel($this->admin);

        Livewire::test(SearchListings::class)
            ->assertSet('type', 'products')
            ->assertCanSeeTableRecords([$this->coat])
            ->assertTableColumnStateSet('meta_title', 'Alpha Coat', $this->coat)
            ->assertTableColumnStateSet('seo_status', 'default', $this->coat)
            ->set('type', 'brands')
            ->assertCanSeeTableRecords([$brand])
            ->assertTableColumnStateSet('meta_title', 'Jaquar', $brand)
            ->set('type', 'pages')
            ->assertCanSeeTableRecords([$page])
            ->set('type', 'nonsense')
            ->assertSet('type', 'products');
    }

    public function test_editing_a_listing_saves_the_fields_and_filters_by_status(): void
    {
        $other = Product::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Alpha Scarf', 'slug' => 'alpha-scarf', 'price' => 300, 'is_active' => true,
        ]);
        $this->enterClientPanel($this->admin);

        Livewire::test(SearchListings::class)
            ->callAction(TestAction::make('editSeo')->table($this->coat), [
                'meta_title' => 'Wool coats for winter',
                'meta_description' => 'Hand-finished merino coat.',
                'noindex' => false,
            ])
            ->assertHasNoFormErrors()
            ->filterTable('seo_status', 'custom')
            ->assertCanSeeTableRecords([$this->coat])
            ->assertCanNotSeeTableRecords([$other])
            ->filterTable('seo_status', 'default')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$this->coat]);

        $this->assertSame('Wool coats for winter', $this->coat->fresh()->seoTitle());
    }

    public function test_each_tab_has_the_filters_of_its_own_list(): void
    {
        $hidden = Product::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Old Scarf', 'slug' => 'old-scarf', 'price' => 300, 'is_active' => false,
        ]);
        $parent = Category::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Men', 'slug' => 'men']);
        $child = Category::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'men-shirts', 'parent_id' => $parent->id]);
        $this->enterClientPanel($this->admin);

        Livewire::test(SearchListings::class)
            ->assertTableFilterExists('categories')
            ->assertTableFilterExists('is_new')
            ->filterTable('record', [$this->coat->id])
            ->assertCanSeeTableRecords([$this->coat])
            ->assertCanNotSeeTableRecords([$hidden])
            ->resetTableFilters()
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$hidden])
            ->assertCanNotSeeTableRecords([$this->coat])
            ->set('type', 'categories')
            ->filterTable('record', [$parent->id])
            ->assertCanSeeTableRecords([$parent, $child], inOrder: false)
            ->resetTableFilters()
            ->assertTableFilterExists('parent_id')
            ->filterTable('parent_id', true)
            ->assertCanSeeTableRecords([$child])
            ->assertCanNotSeeTableRecords([$parent])
            ->set('type', 'pages')
            ->assertTableFilterExists('template');
    }

    public function test_the_category_picker_shows_the_categories_above_each_option_on_the_right(): void
    {
        $men = Category::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Men', 'slug' => 'men']);
        $shirts = Category::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => 'Shirts', 'slug' => 'men-shirts', 'parent_id' => $men->id]);
        $this->enterClientPanel($this->admin);

        $page = Livewire::test(SearchListings::class)->set('type', 'categories');
        $filter = fn () => $page->instance()->getTable()->getFilter('record');

        $this->assertSame('Men', $filter()->getOptions()[$men->id]);
        $this->assertSame('<span class="fi-cat-option">Shirts<span class="fi-cat-option-path">Men</span></span>', $filter()->getOptions()[$shirts->id]);

        // The chip above the table is plain text, not the option's markup.
        $page->filterTable('record', [$men->id, $shirts->id]);
        $this->assertSame(['Category: Men & Men › Shirts'], array_map(fn ($indicator) => $indicator->getLabel(), $filter()->getIndicators()));

        // Other tabs keep their plain names.
        $page->set('type', 'products');
        $this->assertSame('Alpha Coat', $filter()->getOptions()[$this->coat->id] ?? null);
    }

    public function test_seo_settings_save_to_the_seo_group(): void
    {
        $this->enterClientPanel($this->admin);

        Livewire::test(SeoSettings::class)
            ->set('data.seo.title_suffix', ' — Alpha')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(' — Alpha', Setting::get('seo.title_suffix'));
    }
}
