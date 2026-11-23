<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\Testimonial;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Testimonials are put in order by dragging rows in the testimonials list; those sharing a place show the highest rated first, then newest. */
class TestimonialOrderTest extends TestCase
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

    private function testimonial(string $name, int $rating = 5): Testimonial
    {
        return Testimonial::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => $this->template, 'name' => $name, 'rating' => $rating, 'body' => 'Quick and tidy.', 'is_active' => true]);
    }

    public function test_the_testimonial_form_has_no_sort_order_box(): void
    {
        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@pipes.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());

        Livewire::test(CreateTestimonial::class)->assertFormFieldExists('name')->assertFormFieldDoesNotExist('sort_order');

        // Nor a column for it in the list: the order is seen and changed with the reorder button.
        Livewire::test(ListTestimonials::class)->assertTableColumnDoesNotExist('sort_order');
    }

    public function test_testimonials_are_in_the_dragged_order_then_highest_rated_then_newest(): void
    {
        $placed = $this->testimonial('Placed', 3);
        $placed->update(['sort_order' => 1]);
        $olderFive = $this->testimonial('Older five', 5);
        $four = $this->testimonial('Four', 4);
        $newerFive = $this->testimonial('Newer five', 5);
        $newestThree = $this->testimonial('Newest three', 3);

        // The unplaced ones sit at 0: 5 stars (newer first), then 4, then 3, then the one placed
        // at 1, which keeps its place whatever its rating.
        $this->assertSame(
            [$newerFive->id, $olderFive->id, $four->id, $newestThree->id, $placed->id],
            Testimonial::withoutGlobalScopes()->where('client_id', $this->client->id)->active()->pluck('id')->all(),
        );
    }
}
