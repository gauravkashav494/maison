<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\ServiceAreas\Pages\CreateServiceArea;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ServiceArea;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Projects and service areas are put in order by dragging rows in their lists, not typed on the
 * form. Projects sharing a place show the most recently completed first; areas show A to Z.
 */
class ProjectAndAreaOrderTest extends TestCase
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

    private function project(string $title, ?string $completed = null): Project
    {
        return Project::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => $this->template, 'title' => $title, 'completed_on' => $completed, 'is_active' => true]);
    }

    private function area(string $name): ServiceArea
    {
        return ServiceArea::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => $this->template, 'name' => $name, 'is_active' => true]);
    }

    public function test_the_forms_have_no_sort_order_box(): void
    {
        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@pipes.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());

        Livewire::test(CreateProject::class)->assertFormFieldExists('title')->assertFormFieldDoesNotExist('sort_order');
        Livewire::test(CreateServiceArea::class)->assertFormFieldExists('name')->assertFormFieldDoesNotExist('sort_order');
    }

    public function test_projects_are_in_the_dragged_order_then_most_recently_completed(): void
    {
        $placed = $this->project('Placed', '2026-11-01');
        $placed->update(['sort_order' => 1]);
        $older = $this->project('Older job', '2026-03-10');
        $recent = $this->project('Recent job', '2026-10-05');
        $undatedFirst = $this->project('Undated, added first');
        $undatedLater = $this->project('Undated, added later');

        // At 0: the most recently completed first, then those with no date (newest added first),
        // then the one placed at 1 whatever its date.
        $this->assertSame(
            [$recent->id, $older->id, $undatedLater->id, $undatedFirst->id, $placed->id],
            Project::withoutGlobalScopes()->where('client_id', $this->client->id)->active()->pluck('id')->all(),
        );
    }

    public function test_service_areas_are_in_the_dragged_order_then_a_to_z(): void
    {
        $placed = $this->area('Placed');
        $placed->update(['sort_order' => 1]);
        $zirakpur = $this->area('Zirakpur');
        $mohali = $this->area('Mohali');

        $this->assertSame(
            [$mohali->id, $zirakpur->id, $placed->id],
            ServiceArea::withoutGlobalScopes()->where('client_id', $this->client->id)->active()->pluck('id')->all(),
        );
    }
}
