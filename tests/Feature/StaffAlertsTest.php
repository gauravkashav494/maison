<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientRole;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\Storefront;
use App\Support\StaffAlerts;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The client panel's notification bell: what shoppers do that the team should look at shows up
 * for the staff who may open that module, and never for another client's team.
 */
class StaffAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private ClientUser $admin;

    private ClientUser $orderStaff;

    private ClientUser $catalogueStaff;

    private ClientUser $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $other = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->admin = $this->staff($this->client, 'Priya Sharma', ClientUser::ROLE_CLIENT_ADMIN);
        $this->orderStaff = $this->staff($this->client, 'Rahul Verma', ClientUser::ROLE_ADMIN_STAFF, ['orders.view', 'reviews.view', 'messages.view']);
        $this->catalogueStaff = $this->staff($this->client, 'Kiran Rao', ClientUser::ROLE_ADMIN_STAFF, ['products.view']);
        $this->otherAdmin = $this->staff($other, 'Beta Admin', ClientUser::ROLE_CLIENT_ADMIN);

        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => [
            'shipping_methods' => [['code' => 'standard', 'name' => 'Standard', 'cost' => 0]],
        ] + config('client_defaults.checkout')]);
        Cache::forget('settings.all');
    }

    private function staff(Client $client, string $name, string $role, array $permissions = []): ClientUser
    {
        $roleId = $permissions ? ClientRole::create(['client_id' => $client->id, 'name' => $name.' role', 'permissions' => $permissions])->id : null;

        return ClientUser::create([
            'client_id' => $client->id, 'name' => $name, 'email' => str($name)->slug().'@'.$client->subdomain.'.test',
            'password' => 'password123', 'role' => $role, 'client_role_id' => $roleId, 'is_active' => true,
        ]);
    }

    private function product(): Product
    {
        return Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Linen shirt', 'slug' => 'linen-shirt',
            'price' => 1250, 'stock' => 5, 'is_active' => true,
        ]);
    }

    private function placeOrder(): Order
    {
        $product = $this->product();
        $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', ['product_id' => $product->id, 'qty' => 2])->assertSuccessful();
        $items = session('cart.items');
        $this->flushSession();

        $customer = Customer::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'email' => 'asha@example.test', 'name' => 'Asha Rao', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($customer, 'web')
            ->withSession([Client::SESSION_KEY => 'alpha', 'cart.items' => $items])
            ->post('/checkout', [
                'email' => 'asha@example.test', 'phone' => '9876543210', 'shipping_name' => 'Asha Rao',
                'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
                'shipping_postal_code' => '400001', 'shipping_country' => 'India',
                'shipping_method' => 'standard', 'payment_method' => 'cod',
            ])->assertRedirect();

        return Order::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    /** @return list<string> the titles in this account's bell, newest first */
    private function bell(ClientUser $user): array
    {
        return $user->notifications()->get()->map(fn ($n) => $n->data['title'] ?? null)->all();
    }

    public function test_a_new_order_shows_for_the_staff_who_handle_orders(): void
    {
        $order = $this->placeOrder();

        $title = 'New order '.$order->number;
        $this->assertSame([$title], $this->bell($this->admin));
        $this->assertSame([$title], $this->bell($this->orderStaff));
        // No orders permission, or another client's team: nothing.
        $this->assertSame([], $this->bell($this->catalogueStaff));
        $this->assertSame([], $this->bell($this->otherAdmin));

        $alert = $this->admin->notifications()->first();
        $this->assertSame('Asha Rao · ₹2,500.00 · Cash on delivery', $alert->data['body']);
        $this->assertSame('/client/orders/'.$order->getKey(), $alert->data['actions'][0]['url']);
        $this->assertNull($alert->read_at);
    }

    public function test_a_customer_cancelling_shows_with_their_reason(): void
    {
        $order = $this->placeOrder();
        $customer = Customer::withoutGlobalScopes()->where('email', 'asha@example.test')->firstOrFail();

        $this->actingAs($customer, 'web')->withSession([Client::SESSION_KEY => 'alpha'])
            ->post(route('orders.cancel', $order->number), ['reason' => 'Ordered the wrong size'])
            ->assertSessionHas('order_cancelled');

        $title = 'Order '.$order->number.' cancelled by the customer';
        $this->assertContains($title, $this->bell($this->admin));
        $alert = $this->admin->notifications()->get()->firstWhere('data.title', $title);
        $this->assertSame('Ordered the wrong size', $alert->data['body']);
    }

    public function test_staff_cancelling_an_order_raises_no_alert(): void
    {
        $order = $this->placeOrder();
        $this->admin->notifications()->delete();

        $order->transitionTo(Order::STATUS_CANCELLED, 'Out of stock', $this->admin);

        $this->assertSame([], $this->bell($this->admin));
    }

    public function test_a_review_waiting_for_approval_shows_and_a_published_one_does_not(): void
    {
        $this->product();
        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':reviews'], ['value' => ['auto_publish_verified' => true]]);
        Cache::forget('settings.all');

        // A guest's review waits for approval.
        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/product/linen-shirt/reviews', [
            'name' => 'Meera', 'rating' => 4, 'body' => 'Lovely fabric, fits well.',
        ])->assertRedirect();

        $this->assertSame(['Review waiting for approval'], $this->bell($this->admin));
        $this->assertSame('4★ on Linen shirt by Meera', $this->admin->notifications()->first()->data['body']);
        $this->assertSame([], $this->bell($this->catalogueStaff));
    }

    public function test_a_contact_message_shows(): void
    {
        $this->withSession([Client::SESSION_KEY => 'alpha'])->post('/contact', [
            'name' => 'Meera Iyer', 'email' => 'meera@example.test', 'subject' => 'Bulk order', 'message' => 'Can I order 40 shirts for my team?',
        ])->assertRedirect();

        $this->assertSame(['New message from Meera Iyer'], $this->bell($this->orderStaff));
        $this->assertSame('Bulk order', $this->orderStaff->notifications()->first()->data['body']);
    }

    public function test_a_booking_shows_for_staff_with_service_requests(): void
    {
        $this->client->update(['modules' => array_values(array_unique([...($this->client->modules ?? []), 'service_requests']))]);
        $request = ServiceRequest::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'reference' => 'SR-1001', 'type' => 'emergency', 'template' => 'fashion',
            'service_name' => 'Burst pipe', 'name' => 'Arjun', 'phone' => '9876543210',
        ]);

        StaffAlerts::serviceRequest($request);

        $this->assertContains('New emergency SR-1001', $this->bell($this->admin->fresh()));
        $this->assertSame([], $this->bell($this->orderStaff));
    }

    public function test_inactive_staff_get_no_alerts(): void
    {
        $this->orderStaff->update(['is_active' => false]);

        $this->placeOrder();

        $this->assertSame([], $this->bell($this->orderStaff));
    }

    public function test_the_client_panel_has_the_bell(): void
    {
        $panel = Filament::getPanel('client');

        $this->assertTrue($panel->hasDatabaseNotifications());
        $this->assertSame('30s', $panel->getDatabaseNotificationsPollingInterval());
    }
}
