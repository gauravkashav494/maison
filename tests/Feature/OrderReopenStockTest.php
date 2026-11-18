<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Exceptions\InsufficientStockException;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Reopening a cancelled order takes its goods off the shelf again, so it is refused when they
 * have been sold since: nothing changes (status, history or stock) until they are restocked.
 */
class OrderReopenStockTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function product(string $name, int $stock, array $attributes = []): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->toString(),
            'price' => 1000, 'stock' => $stock, 'is_active' => true,
        ]);
    }

    /** A cancelled order for these lines, its goods already back in stock. */
    private function cancelledOrder(array $lines): Order
    {
        $order = Order::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'number' => 'ME-'.strtoupper(substr(md5((string) mt_rand()), 0, 8)),
            'email' => 'shopper@alpha.test', 'status' => 'confirmed', 'shipping_name' => 'Shopper', 'shipping_line1' => '1 Test Street',
            'shipping_city' => 'Mumbai', 'shipping_state' => 'MH', 'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'subtotal' => 1000, 'total' => 1000,
        ]);
        foreach ($lines as [$product, $qty, $options]) {
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name, 'price' => 1000, 'qty' => $qty, 'total' => 1000 * $qty, 'options' => $options]);
        }
        $order->setStatus(Order::STATUS_CANCELLED, 'Out of area');

        return $order->refresh();
    }

    public function test_reopening_takes_the_goods_off_the_shelf_when_they_are_still_there(): void
    {
        $vase = $this->product('Glass vase', 0);
        $order = $this->cancelledOrder([[$vase, 3, null]]);
        $this->assertSame(3, $vase->refresh()->stock);

        $order->setStatus('confirmed');

        $this->assertSame('confirmed', $order->refresh()->status);
        $this->assertSame(0, $vase->refresh()->stock);
    }

    public function test_reopening_is_refused_when_the_goods_were_sold_since(): void
    {
        $vase = $this->product('Glass vase', 0);
        $shirt = $this->product('Linen shirt', 0, [
            'has_variants' => true,
            'variant_attributes' => [['name' => 'Size', 'values' => ['S', 'M']]],
            'variants' => [
                ['options' => ['Size' => 'S'], 'price' => 1000, 'stock' => 0, 'is_active' => true],
                ['options' => ['Size' => 'M'], 'price' => 1000, 'stock' => 0, 'is_active' => true],
            ],
        ]);
        $order = $this->cancelledOrder([[$vase, 3, null], [$shirt, 2, ['Size' => 'M']]]);
        $history = $order->status_history;

        // Sold to someone else after the cancellation: one vase and one shirt in M are left.
        $vase->adjustStock(-2);
        $shirt->refresh()->adjustStock(-1, ['Size' => 'M']);

        try {
            $order->setStatus('confirmed');
            $this->fail('Reopening should have been refused.');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('Glass vase: 3 needed, 1 in stock', $e->getMessage());
            $this->assertStringContainsString('Linen shirt (M): 2 needed, 1 in stock', $e->getMessage());
        }

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame($history, $order->status_history);
        $this->assertSame(1, $vase->refresh()->stock);
        $this->assertSame(1, $shirt->refresh()->stockFor(['Size' => 'M']));
    }

    public function test_a_line_whose_product_was_deleted_does_not_block_reopening(): void
    {
        $vase = $this->product('Glass vase', 0);
        $gone = $this->product('Old lamp', 0);
        $order = $this->cancelledOrder([[$vase, 1, null], [$gone, 1, null]]);
        $gone->delete();

        $order->setStatus('confirmed');

        $this->assertSame('confirmed', $order->refresh()->status);
        $this->assertSame(0, $vase->refresh()->stock);
    }

    public function test_the_edit_form_says_why_and_saves_nothing(): void
    {
        $vase = $this->product('Glass vase', 0);
        $order = $this->cancelledOrder([[$vase, 3, null]]);
        $vase->adjustStock(-3);

        $staff = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($staff, 'client');
        app(ClientContext::class)->resolveForIdentity($staff);
        app(StoreContext::class)->resolve($staff, $this->client->templateId());
        Filament::setCurrentPanel(Filament::getPanel('client'));

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['status' => 'confirmed', 'tracking_number' => 'AWB1234'])
            ->call('save')
            ->assertNotified('Not enough stock');

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertNull($order->tracking_number);
        $this->assertSame(0, $vase->refresh()->stock);
    }
}
