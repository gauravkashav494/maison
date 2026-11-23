<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Brands\Pages\EditBrand;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Brand;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panel toasts name the record and say what happened ('Brand "Acme" updated' / 'Changed: name.'),
 * instead of a bare "Saved" or "Deleted".
 */
class RecordToastTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $admin = ClientUser::create(['client_id' => $this->alpha->id, 'name' => 'Priya Sharma', 'email' => 'priya@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    private function brand(string $name): Brand
    {
        return Brand::withoutGlobalScopes()->create(['client_id' => $this->alpha->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->value()]);
    }

    private function product(string $name): Product
    {
        return Product::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion',
            'name' => $name, 'slug' => str($name)->slug()->value(), 'price' => 500, 'stock' => 5, 'is_active' => true,
        ]);
    }

    public function test_creating_names_the_new_record(): void
    {
        Livewire::test(CreateBrand::class)
            ->fillForm(['name' => 'Acme Mills'])
            ->call('create')
            ->assertNotified(Notification::make()->success()->title('Brand "Acme Mills" created'));
    }

    public function test_saving_names_the_record_and_the_fields_that_changed(): void
    {
        $brand = $this->brand('Acme');

        Livewire::test(EditBrand::class, ['record' => $brand->getRouteKey()])
            ->fillForm(['name' => 'Acme Mills'])
            ->call('save')
            ->assertNotified(Notification::make()->success()->title('Brand "Acme Mills" updated')->body('Changed: name.'));
    }

    public function test_saving_without_changes_says_so(): void
    {
        $brand = $this->brand('Acme');

        Livewire::test(EditBrand::class, ['record' => $brand->getRouteKey()])
            ->call('save')
            ->assertNotified(Notification::make()->info()->title('No changes made')->body('Brand "Acme" is already up to date.'));
    }

    public function test_delete_restore_and_delete_permanently_name_the_record(): void
    {
        $brand = $this->brand('Acme');

        Livewire::test(EditBrand::class, ['record' => $brand->getRouteKey()])
            ->callAction('delete')
            ->assertNotified(Notification::make()->success()->title('Brand "Acme" deleted')->body('Moved to the trash, so it can still be restored.'));

        Livewire::test(EditBrand::class, ['record' => $brand->getRouteKey()])
            ->callAction('restore')
            ->assertNotified(Notification::make()->success()->title('Brand "Acme" restored')->body('It’s back in the Brands list.'));

        $brand->delete();
        Livewire::test(EditBrand::class, ['record' => $brand->getRouteKey()])
            ->callAction('forceDelete')
            ->assertNotified(Notification::make()->success()->title('Brand "Acme" deleted permanently')->body('This can’t be undone.'));
    }

    public function test_a_bulk_delete_counts_and_lists_the_records(): void
    {
        $records = [$this->product('Linen Shirt'), $this->product('Silk Saree'), $this->product('Wool Coat'), $this->product('Cotton Kurta')];

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('delete', $records)
            ->assertNotified(Notification::make()->success()->title('4 products deleted')
                ->body('Cotton Kurta, Wool Coat, Silk Saree and 1 more. Moved to the trash, so they can still be restored.'));
    }

    public function test_settings_name_the_page_and_the_fields_that_changed(): void
    {
        Livewire::test(SiteSettings::class)
            ->set('data.site.name', 'Alpha Atelier')
            ->call('save')
            ->assertNotified(Notification::make()->success()->title('Site settings updated')->body('Changed: site name.'));

        Livewire::test(SiteSettings::class)
            ->call('save')
            ->assertNotified(Notification::make()->info()->title('No changes made')->body('Nothing has changed since the last update.'));
    }
}
