<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Filament\Resources\Taxes\Pages\ListTaxes;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\Tax;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every table offers "Created at" and "Updated at" in its column menu, hidden until switched on
 * (App\Filament\Support\TimestampColumns); a table that already shows one keeps its own.
 */
class TimestampColumnsTest extends TestCase
{
    use RefreshDatabase;

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
        Tax::withoutGlobalScopes()->create(['client_id' => $client->id, 'template' => 'fashion', 'name' => 'GST 12%', 'rate' => 12]);

        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    public function test_a_table_without_them_gets_both_hidden_until_switched_on(): void
    {
        // Promo codes show neither date of their own.
        $page = Livewire::test(ListCoupons::class)
            ->assertTableColumnExists('created_at')
            ->assertTableColumnExists('updated_at');

        // Listed in the column menu, switched off.
        $menu = collect($page->instance()->tableColumns)->keyBy('name');
        foreach (['created_at' => 'Created at', 'updated_at' => 'Updated at'] as $name => $label) {
            $this->assertSame([$label, false], [$menu[$name]['label'] ?? null, $menu[$name]['isToggled'] ?? null], $name);
        }

        $table = $page->instance()->getTable();
        foreach (['created_at', 'updated_at'] as $name) {
            $this->assertTrue($table->getColumn($name)->isToggledHiddenByDefault());
        }

        // Sorting by one works like any other column.
        $page->sortTable('created_at', 'desc')->assertHasNoErrors();
    }

    public function test_apply_columns_closes_the_columns_menu(): void
    {
        foreach ([ListProducts::class, ListCoupons::class, ListOrders::class] as $page) {
            $this->assertStringContainsString('applyTableColumnManager(); close()', Livewire::test($page)->html(), $page);
        }
    }

    public function test_a_table_that_already_shows_one_keeps_its_own(): void
    {
        // Products have their own "Updated" column, and gain only "Created at".
        $products = Livewire::test(ListProducts::class)->instance()->getTable();
        $this->assertSame('Created at', $products->getColumn('created_at')->getLabel());
        // Taxes keep their own "Updated" column and gain "Created at", just before it.
        $menu = collect(Livewire::test(ListTaxes::class)->instance()->tableColumns)->pluck('label', 'name');
        $this->assertSame(['Created at', 'Updated'], [$menu['created_at'], $menu['updated_at']]);
        $names = $menu->keys()->all();
        $this->assertSame(array_search('updated_at', $names, true) - 1, array_search('created_at', $names, true));

        // Orders: "Updated at" goes just after their own "Placed".
        $orders = collect(Livewire::test(ListOrders::class)->instance()->tableColumns)->keyBy('name');
        $names = $orders->keys()->all();
        $this->assertSame(array_search('created_at', $names, true) + 1, array_search('updated_at', $names, true));

        // And Dispatched / Delivered are in the menu too, off until switched on.
        foreach (['invoiced_at' => 'Invoiced', 'dispatched_at' => 'Dispatched', 'delivered_at' => 'Delivered', 'cancelled_at' => 'Cancelled'] as $name => $label) {
            $this->assertSame([$label, false], [$orders[$name]['label'], $orders[$name]['isToggled']]);
        }

        // Orders show their date as "Placed", which stays.
        $orders = Livewire::test(ListOrders::class)->instance()->getTable();
        $this->assertSame('Placed', $orders->getColumn('created_at')->getLabel());
        $this->assertSame('Updated at', $orders->getColumn('updated_at')->getLabel());
    }
}
