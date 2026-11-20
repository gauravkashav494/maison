<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Services are put in order by dragging rows in the services list; those sharing a place show A to Z. */
class ServiceOrderTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private string $template = 'plumbing-services';

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Plumbing', 'slug' => $this->template, 'template' => $this->template, 'is_active' => true]);
        $this->client = Client::create(['name' => 'Pipes', 'subdomain' => 'pipes', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function service(string $name): Service
    {
        return Service::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => $this->template, 'name' => $name, 'excerpt' => 'Fixed fast.', 'is_active' => true]);
    }

    public function test_the_service_form_has_no_sort_order_box(): void
    {
        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@pipes.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());

        Livewire::test(CreateService::class)->assertFormFieldExists('name')->assertFormFieldDoesNotExist('sort_order');

        // Nor a column for it in the list: the order is seen and changed with the reorder button.
        Livewire::test(ListServices::class)->assertTableColumnDoesNotExist('sort_order');
    }

    public function test_popular_services_come_first_then_the_dragged_order(): void
    {
        $first = $this->service('First');
        $second = $this->service('Second');
        $second->update(['sort_order' => 1]);
        $popularLate = $this->service('Popular late');
        $popularLate->update(['sort_order' => 5, 'is_popular' => true]);
        $popularEarly = $this->service('Popular early');
        $popularEarly->update(['sort_order' => 2, 'is_popular' => true]);

        // Popular ones first whatever their place, each group in the dragged order.
        $this->assertSame(
            [$popularEarly->id, $popularLate->id, $first->id, $second->id],
            Service::withoutGlobalScopes()->where('client_id', $this->client->id)->active()->popularFirst()->pluck('id')->all(),
        );
    }

    public function test_services_are_in_the_dragged_order_then_a_to_z(): void
    {
        $placed = $this->service('Placed');
        $placed->update(['sort_order' => 1]);
        $tap = $this->service('Tap Repair');
        $drain = $this->service('Drain Cleaning');

        // Both unplaced ones sit at 0: A to Z, then the one placed at 1.
        $this->assertSame([$drain->id, $tap->id, $placed->id], Service::withoutGlobalScopes()->where('client_id', $this->client->id)->active()->pluck('id')->all());
    }
}
