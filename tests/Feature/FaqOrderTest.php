<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** FAQs are put in order by dragging rows in the FAQs list; questions sharing a place keep the order they were written. */
class FaqOrderTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function faq(string $question): Faq
    {
        return Faq::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'category' => 'General', 'question' => $question, 'answer' => 'Yes.', 'is_active' => true]);
    }

    public function test_the_faq_form_has_no_sort_order_box(): void
    {
        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());

        Livewire::test(CreateFaq::class)->assertFormFieldExists('question')->assertFormFieldDoesNotExist('sort_order');

        // Nor a column for it in the list: the order is seen and changed with the reorder button.
        Livewire::test(ListFaqs::class)->assertTableColumnDoesNotExist('sort_order');
    }

    public function test_faqs_are_in_the_dragged_order_then_the_order_they_were_written(): void
    {
        $placed = $this->faq('Do you ship abroad?');
        $placed->update(['sort_order' => 1]);
        $first = $this->faq('Zero: written first');
        $second = $this->faq('A second question');

        // Both unplaced ones sit at 0: oldest first (not A to Z), then the one placed at 1.
        $this->assertSame([$first->id, $second->id, $placed->id], Faq::withoutGlobalScopes()->where('client_id', $this->client->id)->active()->pluck('id')->all());
    }
}
