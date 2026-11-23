<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\Tax;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A product save that is refused says what is missing and on which tab, and the tabs with
 * problems show a red count, rather than only switching tab.
 */
class ProductFormErrorsTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $admin = ClientUser::create([
            'client_id' => $client->id, 'name' => 'Priya Sharma', 'email' => 'priya@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->category = Category::withoutGlobalScopes()->create(['client_id' => $client->id, 'template' => 'fashion', 'name' => 'Coats', 'slug' => 'coats', 'is_active' => true]);
        Tax::withoutGlobalScopes()->create(['client_id' => $client->id, 'template' => 'fashion', 'name' => 'GST 12%', 'rate' => 12]);

        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    /** The last toast sent, read as Filament's own assertNotified() reads them. */
    private function lastNotification(): Notification
    {
        $toasts = new Notifications;
        $toasts->mount();

        return $toasts->notifications->last();
    }

    public function test_creating_with_only_the_details_filled_names_what_is_missing_on_which_tab(): void
    {
        $form = Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Wool Coat', 'category_level_0' => $this->category->id, 'price_incl_tax' => null, 'price_excl_tax' => null])
            ->call('create')
            ->assertHasFormErrors(['price_incl_tax', 'price_excl_tax', 'tax_id']);

        $notification = $this->lastNotification();
        $this->assertSame('Some things need fixing before saving', $notification->getTitle());
        $body = $notification->getBody();
        $this->assertStringContainsString('<strong>Pricing &amp; Variants:</strong>', $body);
        $this->assertStringContainsString('Price incl. tax', $body);
        $this->assertStringContainsString('Tax', $body);
        $this->assertStringNotContainsString('Details:', $body);

        $this->assertSame(3, $form->instance()->errorCountOnTab('Pricing & Variants'));
        $this->assertNull($form->instance()->errorCountOnTab('Details'));
    }

    public function test_adding_a_product_has_next_on_the_first_tabs_and_create_from_pricing_on(): void
    {
        $page = Livewire::test(CreateProduct::class);
        $html = $page->html();

        // Next on Details and Media, Create (and Create & add another) on Pricing & Variants.
        $this->assertSame(2, substr_count($html, 'getTabs().indexOf(tab) + 1'));
        $this->assertStringContainsString('$wire.create()', $html);
        $this->assertStringContainsString('$wire.createAnother()', $html);
        // No Create button of its own under the form.
        $this->assertSame([], array_values(array_filter(
            array_map(fn ($action) => $action->getName(), (fn () => $this->getFormActions())->call($page->instance())),
            fn ($name) => $name !== 'cancel',
        )));

        // The Create button saves the product like the usual one did.
        $page->fillForm(['name' => 'Wool Coat', 'category_level_0' => $this->category->id, 'price_incl_tax' => 1120, 'tax_id' => Tax::first()->id, 'stock' => 5])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(1, \App\Models\Product::where('name', 'Wool Coat')->count());
    }

    public function test_editing_keeps_its_save_button_and_no_next_or_create(): void
    {
        $product = \App\Models\Product::withoutGlobalScopes()->create([
            'client_id' => $this->category->client_id, 'template' => 'fashion', 'name' => 'Old Coat', 'slug' => 'old-coat',
            'price' => 1000, 'stock' => 3, 'is_active' => true, 'category_id' => $this->category->id, 'tax_id' => Tax::first()->id,
        ]);

        $html = Livewire::test(\App\Filament\Resources\Products\Pages\EditProduct::class, ['record' => $product->getRouteKey()])->html();
        $this->assertStringNotContainsString('getTabs().indexOf(tab) + 1', $html);
        $this->assertStringNotContainsString('$wire.createAnother()', $html);
    }

    public function test_a_missing_name_and_category_point_to_the_details_tab(): void
    {
        $form = Livewire::test(CreateProduct::class)
            ->fillForm(['name' => '', 'price_incl_tax' => 1000, 'tax_id' => Tax::first()->id])
            ->call('create')
            ->assertHasFormErrors(['name', 'category_level_0']);

        $body = $this->lastNotification()->getBody();
        $this->assertStringContainsString('<strong>Details:</strong>', $body);
        $this->assertStringContainsString('Name', $body);
        $this->assertStringContainsString('Category', $body);
        $this->assertSame(2, $form->instance()->errorCountOnTab('Details'));
    }
}
