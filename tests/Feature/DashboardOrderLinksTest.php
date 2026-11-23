<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The order cards on the client dashboard open the orders list filtered to the figure they show:
 * this month's orders, this month's non-cancelled orders (revenue), and orders still to fulfil.
 */
class DashboardOrderLinksTest extends TestCase
{
    use RefreshDatabase;

    protected ClientUser $admin;

    private array $orders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->admin = ClientUser::create([
            'client_id' => $client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        $thisMonth = now()->startOfMonth()->addHours(3);
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth()->addDays(3);
        foreach ([
            'NEW-CONFIRMED' => ['confirmed', $thisMonth],
            'NEW-PROCESSING' => ['processing', $thisMonth],
            'NEW-SHIPPED' => ['shipped', $thisMonth],
            'NEW-CANCELLED' => ['cancelled', $thisMonth],
            'OLD-DELIVERED' => ['delivered', $lastMonth],
            'OLD-CONFIRMED' => ['confirmed', $lastMonth],
        ] as $number => [$status, $at]) {
            $this->orders[$number] = $this->order($client, $number, $status, $at);
        }
    }

    private function order(Client $client, string $number, string $status, Carbon $at): Order
    {
        $order = Order::withoutGlobalScopes()->create([
            'client_id' => $client->id, 'template' => 'fashion', 'number' => $number, 'email' => 'shopper@alpha.test',
            'status' => $status, 'shipping_name' => 'Shopper', 'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai',
            'shipping_state' => 'MH', 'shipping_postal_code' => '400001', 'shipping_country' => 'India',
            'subtotal' => 1000, 'total' => 1000,
        ]);
        $order->forceFill(['created_at' => $at])->saveQuietly();

        return $order;
    }

    /** The href of the dashboard card with this label. */
    private function cardUrl(string $label): string
    {
        $html = $this->actingAs($this->admin, 'client')->get('/client')->assertSuccessful()->getContent();
        $at = strpos($html, $label);
        $this->assertNotFalse($at, "Card '{$label}' is not on the dashboard");
        // The card is an <a href="…"> wrapping its label: take the last href before the label.
        preg_match_all('/href="([^"]+)"/', substr($html, 0, $at), $m);

        return html_entity_decode(end($m[1]));
    }

    private function assertListShows(string $url, array $visible): void
    {
        $page = $this->actingAs($this->admin, 'client')->get($url)->assertSuccessful();
        foreach (array_keys($this->orders) as $number) {
            in_array($number, $visible, true) ? $page->assertSee($number) : $page->assertDontSee($number);
        }
    }

    public function test_orders_this_month_opens_only_this_months_orders(): void
    {
        $this->assertListShows($this->cardUrl('Orders this month'), ['NEW-CONFIRMED', 'NEW-PROCESSING', 'NEW-SHIPPED', 'NEW-CANCELLED']);
    }

    public function test_revenue_this_month_opens_this_months_orders_without_cancelled(): void
    {
        $this->assertListShows($this->cardUrl('Revenue this month'), ['NEW-CONFIRMED', 'NEW-PROCESSING', 'NEW-SHIPPED']);
    }

    public function test_orders_to_fulfil_opens_confirmed_and_processing_orders_from_any_month(): void
    {
        $this->assertListShows($this->cardUrl('Orders to fulfil'), ['NEW-CONFIRMED', 'NEW-PROCESSING', 'OLD-CONFIRMED']);
    }
}
