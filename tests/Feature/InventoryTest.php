<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Clients\Scopes\ClientOwned;
use App\Filament\Pages\StockLevels;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storefront;
use App\Services\Inventory;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Inventory: every move of stock is in the stock history with why (sale, cancelled order,
 * stock received, count…), staff adjust stock on Stock levels, and only the right staff of the
 * right client see any of it.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private Client $beta;

    private ClientUser $admin;

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
    }

    private function product(string $name, int $stock, array $attributes = [], ?Client $client = null): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => ($client ?? $this->alpha)->id, 'template' => 'fashion', 'name' => $name,
            'slug' => str($name)->slug()->toString(), 'price' => 1000, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    private function shirt(): Product
    {
        // The product's stock is its variants' total, as the product form saves it.
        return $this->product('Linen Shirt', 14, [
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['S', 'M']]],
            'variants' => [
                ['options' => ['Size' => 'S'], 'price' => 1000, 'stock' => 4, 'is_active' => true],
                ['options' => ['Size' => 'M'], 'price' => 1000, 'stock' => 10, 'is_active' => true],
            ],
        ]);
    }

    private function staff(array $permissions): ClientUser
    {
        $key = md5(implode(',', $permissions));
        $role = ClientRole::create(['client_id' => $this->alpha->id, 'name' => 'Role '.$key, 'permissions' => $permissions]);

        return ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Rahul Verma', 'email' => 'rahul-'.$key.'@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'client_role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function enterClientPanel(ClientUser $user): void
    {
        $this->actingAs($user, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($user);
        app(StoreContext::class)->resolve($user, app(ClientContext::class)->templateId());
        // What ApplyAdminScope does on a real request: only this client's rows.
        foreach (StoreContext::SCOPED_MODELS as $model) {
            $model::addGlobalScope(ClientOwned::NAME, new ClientOwned($user->client_id));
        }
    }

    /** The product's movements, oldest first, as [reason, quantity, stock after, variant]. */
    private function movements(Product $product): array
    {
        return StockMovement::withoutGlobalScopes()->where('product_id', $product->id)->orderBy('id')->get()
            ->map(fn (StockMovement $m) => [$m->reason, $m->quantity, $m->stock_after, $m->variant])->all();
    }

    // ---- The stock history --------------------------------------------------------------------

    public function test_a_new_product_starts_its_history_with_its_opening_stock(): void
    {
        $this->assertSame([['opening', 12, 12, null]], $this->movements($this->product('Alpha Coat', 12)));
        $this->assertSame([['opening', 4, 4, 'S'], ['opening', 10, 10, 'M']], $this->movements($this->shirt()));
        $this->assertSame([], $this->movements($this->product('Empty Vase', 0)));
    }

    public function test_sales_and_cancellations_are_in_the_history_with_their_order(): void
    {
        $shirt = $this->shirt();
        $order = Order::withoutGlobalScopes()->create([
            'client_id' => $this->alpha->id, 'template' => 'fashion', 'number' => 'ME-TEST-1', 'email' => 'shopper@alpha.test',
            'status' => 'confirmed', 'shipping_name' => 'Shopper', 'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai',
            'shipping_state' => 'Maharashtra', 'shipping_postal_code' => '400001', 'shipping_country' => 'India', 'subtotal' => 3000, 'total' => 3000,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $shirt->id, 'name' => $shirt->name, 'price' => 1000, 'qty' => 3, 'total' => 3000, 'options' => ['Size' => 'M']]);

        $shirt->adjustStock(-3, ['Size' => 'M'], StockMovement::SALE, $order);
        $this->assertSame(11, $shirt->refresh()->stock);
        $this->assertSame(7, $shirt->variants[1]['stock']);
        $this->assertSame(4, $shirt->lowest_stock);

        $this->enterClientPanel($this->admin);
        $order->setStatus(Order::STATUS_CANCELLED, 'Changed mind');

        $this->assertSame(10, $shirt->refresh()->variants[1]['stock']);
        $sale = StockMovement::withoutGlobalScopes()->where('reason', 'sale')->firstOrFail();
        $this->assertSame(['M', -3, 7, 'ME-TEST-1', null], [$sale->variant, $sale->quantity, $sale->stock_after, $sale->order_number, $sale->by_name]);
        $back = StockMovement::withoutGlobalScopes()->where('reason', 'cancelled')->firstOrFail();
        $this->assertSame(['M', 3, 10, 'Priya Sharma', $this->alpha->id], [$back->variant, $back->quantity, $back->stock_after, $back->by_name, $back->client_id]);
    }

    public function test_stock_changed_on_the_product_form_is_in_the_history(): void
    {
        $coat = $this->product('Alpha Coat', 12);
        $shirt = $this->shirt();
        $this->enterClientPanel($this->admin);

        $coat->update(['stock' => 8]);
        $variants = $shirt->variants;
        $variants[0]['stock'] = 6;
        $variants[1]['price'] = 1200; // not a stock change
        $shirt->update(['variants' => $variants, 'stock' => 16]);

        $this->assertSame([['opening', 12, 12, null], ['edited', -4, 8, null]], $this->movements($coat));
        $this->assertSame([['opening', 4, 4, 'S'], ['opening', 10, 10, 'M'], ['edited', 2, 6, 'S']], $this->movements($shirt));
        $this->assertSame('Priya Sharma', StockMovement::withoutGlobalScopes()->latest('id')->value('by_name'));
    }

    // ---- Adjusting stock ----------------------------------------------------------------------

    public function test_staff_record_stock_received_damaged_and_counted(): void
    {
        $coat = $this->product('Alpha Coat', 12);
        $shirt = $this->shirt();
        $this->enterClientPanel($this->admin);
        $inventory = app(Inventory::class);

        $inventory->adjust($coat, null, StockMovement::RECEIVED, 20, 'Invoice 553');
        $inventory->adjust($coat, null, StockMovement::DAMAGED, 2);
        $inventory->adjust($coat, null, StockMovement::COUNT, 30);
        $inventory->adjust($coat, null, StockMovement::COUNT, 30); // matches the shelf: checked, no change
        $inventory->adjust($shirt, json_encode(['Size' => 'S']), StockMovement::RETURNED, 1);

        $this->assertSame(30, $coat->refresh()->stock);
        $this->assertSame([['opening', 12, 12, null], ['received', 20, 32, null], ['damaged', -2, 30, null], ['count', 0, 30, null], ['count', 0, 30, null]], $this->movements($coat));
        $this->assertSame('Invoice 553', StockMovement::withoutGlobalScopes()->where('reason', 'received')->value('note'));
        $this->assertSame([5, 15], [$shirt->refresh()->variants[0]['stock'], $shirt->stock]);
    }

    public function test_an_adjustment_that_cannot_be_made_changes_nothing(): void
    {
        $coat = $this->product('Alpha Coat', 2);
        $shirt = $this->shirt();
        $this->enterClientPanel($this->admin);
        $inventory = app(Inventory::class);

        foreach ([
            fn () => $inventory->adjust($coat, null, StockMovement::DAMAGED, 3),
            fn () => $inventory->adjust($coat, null, StockMovement::RECEIVED, 0),
            fn () => $inventory->adjust($coat, null, StockMovement::SALE, 1), // not something staff record
            fn () => $inventory->adjust($shirt, null, StockMovement::RECEIVED, 1), // which size?
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('The adjustment should have been refused.');
            } catch (InvalidArgumentException) {
            }
        }

        $this->assertSame(2, $coat->refresh()->stock);
        $this->assertSame(1, StockMovement::withoutGlobalScopes()->where('product_id', $coat->id)->count());
    }

    public function test_the_stock_levels_page_adjusts_stock_and_filters_by_status(): void
    {
        $coat = $this->product('Alpha Coat', 12);
        $vase = $this->product('Glass Vase', 0);
        $scarf = $this->product('Silk Scarf', 3);
        $other = $this->product('Beta Coat', 0, client: $this->beta);
        $this->enterClientPanel($this->admin);

        Livewire::test(StockLevels::class)
            ->assertCanSeeTableRecords([$coat, $vase, $scarf])
            ->assertCanNotSeeTableRecords([$other])
            ->filterTable('stock_status', Inventory::STATUS_OUT)->assertCanSeeTableRecords([$vase])->assertCanNotSeeTableRecords([$coat, $scarf])
            ->filterTable('stock_status', Inventory::STATUS_LOW)->assertCanSeeTableRecords([$scarf])->assertCanNotSeeTableRecords([$coat, $vase])
            ->resetTableFilters()
            ->callAction(TestAction::make('adjust')->table($vase), ['type' => StockMovement::RECEIVED, 'quantity' => 6])
            ->assertHasNoFormErrors()
            ->assertNotified('Stock updated');

        $this->assertSame(6, $vase->refresh()->stock);
    }

    public function test_the_low_stock_level_can_be_changed(): void
    {
        $scarf = $this->product('Silk Scarf', 8);
        $this->enterClientPanel($this->admin);
        $this->assertSame(Inventory::STATUS_IN, Inventory::status($scarf));

        Livewire::test(StockLevels::class)->callAction('lowStockLevel', ['level' => 10])->assertNotified('Low-stock level saved');

        $this->assertSame(10, Inventory::lowStockLevel());
        $this->assertSame(Inventory::STATUS_LOW, Inventory::status($scarf->refresh()));
    }

    // ---- Who sees it --------------------------------------------------------------------------

    public function test_inventory_follows_the_role(): void
    {
        $viewer = $this->staff(['products.view', 'inventory.view']);
        $this->enterClientPanel($viewer);
        $this->assertTrue(StockLevels::canAccess());
        Livewire::test(StockLevels::class)
            ->assertActionHidden('lowStockLevel')
            ->assertActionHidden(TestAction::make('adjust')->table($this->product('Alpha Coat', 1)));

        $this->enterClientPanel($this->staff(['products.view', 'products.update']));
        $this->assertFalse(StockLevels::canAccess());
        $this->get(StockMovementResource::getUrl('index'))->assertForbidden();
    }

    public function test_the_stock_history_shows_only_the_clients_own_movements(): void
    {
        $coat = $this->product('Alpha Coat', 5);
        $other = $this->product('Beta Coat', 7, client: $this->beta);
        $this->enterClientPanel($this->admin);

        $mine = StockMovement::withoutGlobalScopes()->where('product_id', $coat->id)->get();
        $theirs = StockMovement::withoutGlobalScopes()->where('product_id', $other->id)->get();

        Livewire::test(ListStockMovements::class)
            ->assertCanSeeTableRecords($mine)
            ->assertCanNotSeeTableRecords($theirs)
            ->assertActionDoesNotExist('create');
    }

    public function test_switching_the_module_off_hides_inventory_but_keeps_counting_stock(): void
    {
        $this->alpha->update(['modules' => ['orders', 'customers', 'products']]);
        $coat = $this->product('Alpha Coat', 5);
        $this->enterClientPanel($this->admin->refresh());

        $this->assertFalse(StockLevels::canAccess());
        $coat->adjustStock(-1, [], StockMovement::SALE);
        $this->assertSame(4, $coat->refresh()->stock);
    }

    public function test_the_stock_history_filters_by_a_date_range(): void
    {
        $coat = $this->product('Alpha Coat', 5);
        $old = StockMovement::withoutGlobalScopes()->where('product_id', $coat->id)->firstOrFail();
        $old->forceFill(['created_at' => now()->subDays(20)])->save();
        $this->enterClientPanel($this->admin);
        $recent = app(Inventory::class)->adjust($coat, null, StockMovement::RECEIVED, 3);
        $day = fn (int $ago) => now()->subDays($ago)->format('d/m/Y');

        Livewire::test(ListStockMovements::class)
            ->filterTable('when', ['when' => $day(7).' - '.$day(0)])
            ->assertCanSeeTableRecords([$recent])->assertCanNotSeeTableRecords([$old])
            ->filterTable('when', ['when' => $day(20).' - '.$day(20)])
            ->assertCanSeeTableRecords([$old])->assertCanNotSeeTableRecords([$recent]);
    }
}
