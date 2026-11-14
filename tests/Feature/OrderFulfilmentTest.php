<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manual order fulfilment: Dispatch, Delivered and Cancel.
 *
 * The rules live on the model, so the admin actions, the bulk actions and anything added
 * later all behave the same. These tests pin the allowed moves, the refused ones and the
 * audit trail each change leaves behind.
 */
class OrderFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private ClientUser $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->staff = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
    }

    private function order(string $status = 'confirmed'): Order
    {
        return Order::withoutGlobalScopes()->create([
            'client_id' => $this->client->id,
            'template' => 'fashion',
            'number' => 'ME-'.strtoupper(substr(md5((string) mt_rand()), 0, 8)),
            'email' => 'shopper@alpha.test',
            'status' => $status,
            'shipping_name' => 'Shopper',
            'shipping_line1' => '1 Test Street',
            'shipping_city' => 'Mumbai',
            'shipping_state' => 'MH',
            'shipping_postal_code' => '400001',
            'shipping_country' => 'India',
            'subtotal' => 1000,
            'total' => 1000,
        ]);
    }

    public function test_a_new_order_can_be_dispatched_delivered_or_cancelled(): void
    {
        $order = $this->order();

        $this->assertTrue($order->canTransitionTo(Order::STATUS_DISPATCHED));
        $this->assertTrue($order->canTransitionTo(Order::STATUS_DELIVERED));
        $this->assertTrue($order->canTransitionTo(Order::STATUS_CANCELLED));
        $this->assertTrue($order->canTransitionTo('processing'));
    }

    public function test_dispatching_records_the_status_the_time_and_who_did_it(): void
    {
        $order = $this->order();

        $this->assertTrue($order->transitionTo(Order::STATUS_DISPATCHED, 'Handed to the courier', $this->staff));

        $order->refresh();
        $this->assertSame(Order::STATUS_DISPATCHED, $order->status);
        $this->assertSame('Dispatched', $order->statusLabel());
        $this->assertNotNull($order->dispatched_at);

        $history = $order->status_history;
        $entry = end($history);
        $this->assertSame(Order::STATUS_DISPATCHED, $entry['status']);
        $this->assertSame('Handed to the courier', $entry['note']);
        $this->assertStringContainsString('admin@alpha.test', $entry['by']);
        $this->assertSame($this->staff->id, $entry['by_id']);
        $this->assertNotEmpty($entry['at']);
    }

    public function test_delivering_closes_the_order(): void
    {
        $order = $this->order();
        $order->transitionTo(Order::STATUS_DISPATCHED, null, $this->staff);
        $this->assertTrue($order->transitionTo(Order::STATUS_DELIVERED, null, $this->staff));

        $order->refresh();
        $this->assertNotNull($order->delivered_at);
        $this->assertSame([], $order->allowedTransitions(), 'A delivered order is finished.');
        $this->assertFalse($order->transitionTo(Order::STATUS_CANCELLED, 'Changed their mind', $this->staff));
        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
    }

    public function test_cancelling_records_the_reason_and_ends_the_order(): void
    {
        $order = $this->order();

        $this->assertTrue($order->transitionTo(Order::STATUS_CANCELLED, 'Out of stock', $this->staff));

        $order->refresh();
        $this->assertTrue($order->isCancelled());
        $this->assertSame('Cancelled', $order->statusLabel());
        $this->assertSame('Out of stock', $order->cancellation_reason);
        $this->assertNotNull($order->cancelled_at);

        // Nothing follows a cancellation.
        $this->assertSame([], $order->allowedTransitions());
        $this->assertFalse($order->transitionTo(Order::STATUS_DISPATCHED, null, $this->staff));
        $this->assertFalse($order->transitionTo(Order::STATUS_DELIVERED, null, $this->staff));
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_the_pipeline_never_runs_backwards(): void
    {
        $order = $this->order('out_for_delivery');

        $this->assertFalse($order->canTransitionTo('confirmed'));
        $this->assertFalse($order->canTransitionTo('processing'));
        $this->assertFalse($order->canTransitionTo(Order::STATUS_DISPATCHED));
        $this->assertTrue($order->canTransitionTo(Order::STATUS_DELIVERED));
        $this->assertTrue($order->canTransitionTo(Order::STATUS_CANCELLED));
    }

    public function test_the_whole_history_is_kept_in_order(): void
    {
        $order = $this->order();
        $order->transitionTo('processing', null, $this->staff);
        $order->transitionTo(Order::STATUS_DISPATCHED, null, $this->staff);
        $order->transitionTo(Order::STATUS_DELIVERED, null, $this->staff);

        $statuses = array_column($order->fresh()->statusTimeline(), 'status');
        $this->assertSame(['processing', 'shipped', 'delivered'], $statuses);
        $this->assertSame('Dispatched', $order->fresh()->statusTimeline()[1]['label']);
    }

    public function test_the_order_list_filters_by_status_and_by_date(): void
    {
        $delivered = $this->order();
        $delivered->transitionTo(Order::STATUS_DELIVERED, null, $this->staff);
        $delivered->forceFill(['created_at' => now()->subDays(10)])->save();

        $open = $this->order();
        $open->forceFill(['created_at' => now()])->save();

        // The admin list filters are thin wrappers around these scopes (OrdersTable).
        $all = fn () => Order::withoutGlobalScopes();

        // Status
        $this->assertSame([$delivered->id], $all()->withStatus(Order::STATUS_DELIVERED)->pluck('id')->all());

        // One exact day
        $this->assertSame([$open->id], $all()->placedOn(now()->toDateString())->pluck('id')->all());
        $this->assertSame([$delivered->id], $all()->placedOn(now()->subDays(10)->toDateString())->pluck('id')->all());

        // A range, and each end on its own
        $this->assertSame([$delivered->id], $all()->placedBetween(now()->subDays(12)->toDateString(), now()->subDays(5)->toDateString())->pluck('id')->all());
        $this->assertSame([$open->id], $all()->placedBetween(now()->subDays(1)->toDateString(), null)->pluck('id')->all());
        $this->assertSame([$delivered->id], $all()->placedBetween(null, now()->subDays(5)->toDateString())->pluck('id')->all());

        // Nothing chosen (the state the reset button returns to) filters nothing out.
        $this->assertCount(2, $all()->placedOn(null)->placedBetween(null, null)->withStatus(null)->get());
    }

    public function test_the_order_list_page_opens_with_the_filters_available(): void
    {
        $order = $this->order();
        $this->actingAs($this->staff, 'client');

        $this->get('/client/orders')
            ->assertSuccessful()
            ->assertSee($order->number)
            ->assertSee('Placed on')        // the single-date filter
            ->assertSee('Placed from');     // the range filter
    }

    public function test_the_order_page_shows_the_status_history_as_a_timeline_newest_first(): void
    {
        $order = $this->order();
        $order->forceFill(['status_history' => [['status' => 'confirmed', 'at' => now()->subDay()->toIso8601String(), 'note' => 'Order placed']]])->save();
        $order->transitionTo('processing', null, $this->staff);
        $order->transitionTo(Order::STATUS_DISPATCHED, 'Sent with BlueDart, AWB 1234', $this->staff);
        $this->actingAs($this->staff, 'client');

        $html = $this->get('/client/orders/'.$order->getRouteKey())->assertSuccessful()->getContent();

        $timeline = (string) str($html)->after('Order timeline')->before('</ol>');
        $this->assertStringContainsString('Current', $timeline);
        $this->assertStringContainsString('Sent with BlueDart, AWB 1234', $timeline);
        $this->assertStringContainsString('by Alpha Admin', $timeline);
        $this->assertStringNotContainsString('admin@alpha.test', $timeline, 'Only the name of who made the change, not their email.');
        // Newest first: Dispatched, then Processing, then Order Confirmed.
        $this->assertTrue(strpos($timeline, 'Dispatched') < strpos($timeline, 'Processing'));
        $this->assertTrue(strpos($timeline, 'Processing') < strpos($timeline, 'Order Confirmed'));
    }

    public function test_the_order_page_has_the_store_admin_layout(): void
    {
        $order = $this->order();
        $order->forceFill([
            'phone' => '9876543210', 'payment_method' => 'upi', 'payment_status' => 'paid',
            'shipping_method' => 'Standard', 'discount' => 100, 'coupon_code' => 'TRADE10', 'notes' => 'Leave at reception',
        ])->save();
        $this->order()->forceFill(['total' => 2500])->save(); // the same customer's earlier order
        $this->actingAs($this->staff, 'client');

        $this->get('/client/orders/'.$order->getRouteKey())
            ->assertSuccessful()
            ->assertSee('Order '.$order->number)
            ->assertSeeInOrder(['Order Confirmed', 'Paid', 'Placed'])                  // header badges
            ->assertSee('Dispatch')                                                   // the next step, on the page
            ->assertSeeInOrder(['Items', 'Payment', 'Order timeline'])                 // main column
            ->assertSeeInOrder(['Customer', 'Shipping address', 'Delivery', 'Customer note']) // side cards
            ->assertSee('Discount (TRADE10)')
            ->assertSee('UPI · Paid')
            ->assertSee('2 orders')                                                   // customer history by email
            ->assertSee('Leave at reception')
            ->assertDontSee('Cancelled');                                             // no cancellation card
    }

    public function test_an_order_can_be_dispatched_from_its_own_page(): void
    {
        $order = $this->order();
        $this->actingAs($this->staff, 'client');
        app(\App\Clients\ClientContext::class)->resolveForIdentity($this->staff);
        app(\App\Admin\StoreContext::class)->resolve($this->staff, $this->client->templateId());
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('client'));

        \Livewire\Livewire::test(\App\Filament\Resources\Orders\Pages\ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('dispatch')
            ->callAction('dispatch', ['carrier' => 'BlueDart', 'tracking_number' => 'AWB1234', 'note' => 'Packed'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('dispatch'); // already dispatched, so the button goes

        $order->refresh();
        $this->assertSame(Order::STATUS_DISPATCHED, $order->status);
        $this->assertSame('AWB1234', $order->tracking_number);
    }

    public function test_an_order_of_another_client_cannot_be_fulfilled(): void
    {
        $otherStore = Storefront::create(['name' => 'Grocery Store', 'slug' => 'grocery', 'template' => 'grocery', 'is_active' => true]);
        $other = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $otherStore->id, 'is_active' => true]);
        $theirOrder = Order::withoutGlobalScopes()->create([
            'client_id' => $other->id, 'template' => 'grocery', 'number' => 'ME-OTHER-1',
            'email' => 'shopper@beta.test', 'status' => 'confirmed',
            'shipping_name' => 'Shopper', 'shipping_line1' => '2 Test Street', 'shipping_city' => 'Pune',
            'shipping_state' => 'MH', 'shipping_postal_code' => '411001', 'shipping_country' => 'India',
            'subtotal' => 500, 'total' => 500,
        ]);

        app(\App\Clients\ClientContext::class)->resolveForIdentity($this->staff);
        app(\App\Admin\StoreContext::class)->resolve($this->staff, $this->client->templateId());

        $this->assertFalse($this->staff->can('update', $theirOrder));
    }
}
