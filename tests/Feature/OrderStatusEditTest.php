<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
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
 * On the order edit form an order only moves forward: never back a step, and a delivered or
 * cancelled order is finished (a cancelled one is never reopened, its stock stays put).
 */
class OrderStatusEditTest extends TestCase
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

    private function signInAsStaff(): void
    {
        $staff = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($staff, 'client');
        app(ClientContext::class)->resolveForIdentity($staff);
        app(StoreContext::class)->resolve($staff, $this->client->templateId());
        Filament::setCurrentPanel(Filament::getPanel('client'));
    }

    public function test_the_edit_form_cannot_reopen_a_cancelled_order(): void
    {
        $vase = $this->product('Glass vase', 5);
        $order = $this->cancelledOrder([[$vase, 3, null]]);
        $this->signInAsStaff();

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldIsDisabled('status')
            ->set('data.status', 'confirmed')
            ->call('save');

        // The locked field is not submitted: the order stays cancelled and its stock stays back on the shelf.
        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame(8, $vase->refresh()->stock);
    }

    public function test_the_edit_form_only_moves_an_order_forward(): void
    {
        $vase = $this->product('Glass vase', 5);
        $order = $this->cancelledOrder([[$vase, 1, null]]);
        $order->forceFill(['status' => Order::STATUS_DISPATCHED, 'cancelled_at' => null])->saveQuietly(); // a live, dispatched order
        $this->signInAsStaff();

        $form = Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormFieldIsEnabled('status');

        $options = $form->instance()->form->getComponent('status')->getOptions();
        $this->assertSame(['shipped', 'out_for_delivery', 'delivered', 'cancelled'], array_keys($options));

        $form->set('data.status', 'processing')->call('save')->assertHasFormErrors(['status']);
        $this->assertSame(Order::STATUS_DISPATCHED, $order->refresh()->status);

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['status' => 'out_for_delivery'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('out_for_delivery', $order->refresh()->status);
    }

    public function test_a_sales_order_without_an_address_can_be_saved(): void
    {
        $salesperson = \App\Models\Customer::create([
            'client_id' => $this->client->id, 'name' => 'Ravi Sales', 'phone' => '9000000001', 'is_active' => true, 'is_salesperson' => true,
        ]);
        $order = $this->cancelledOrder([[$this->product('Glass vase', 5), 1, null]]);
        $order->forceFill([
            'status' => 'confirmed', 'cancelled_at' => null, 'salesperson_id' => $salesperson->id,
            'shipping_line1' => '', 'shipping_city' => '', 'shipping_state' => '', 'shipping_postal_code' => '', 'shipping_country' => '',
        ])->saveQuietly();
        $this->signInAsStaff();

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm(['status' => 'processing'])
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame('', $order->shipping_line1);
    }
}
