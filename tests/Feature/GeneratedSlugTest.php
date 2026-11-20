<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\ServiceAreas\Pages\CreateServiceArea;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Post;
use App\Models\Project;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pages, posts, services, service areas and projects have no slug box: the slug is made from the
 * title or name on save (GeneratesSlug), unique within the store and storefront template, and kept
 * when the record is renamed so its address never breaks.
 */
class GeneratedSlugTest extends TestCase
{
    use RefreshDatabase;

    private Client $fashion;

    private Client $plumbing;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $fashion = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $plumbing = Storefront::create(['name' => 'Plumbing', 'slug' => 'plumbing-services', 'template' => 'plumbing-services', 'is_active' => true]);
        $this->fashion = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $fashion->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->plumbing = Client::create(['name' => 'Pipes', 'subdomain' => 'pipes', 'storefront_id' => $plumbing->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function signInTo(Client $client): void
    {
        $admin = ClientUser::create(['client_id' => $client->id, 'name' => 'Admin', 'email' => uniqid().'@test.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    public function test_the_forms_have_no_slug_box(): void
    {
        $this->signInTo($this->fashion);
        Livewire::test(CreatePage::class)->assertFormFieldExists('title')->assertFormFieldDoesNotExist('slug');
        Livewire::test(CreatePost::class)->assertFormFieldExists('title')->assertFormFieldDoesNotExist('slug');

        $this->signInTo($this->plumbing);
        Livewire::test(CreateService::class)->assertFormFieldExists('name')->assertFormFieldDoesNotExist('slug');
        Livewire::test(CreateServiceArea::class)->assertFormFieldExists('name')->assertFormFieldDoesNotExist('slug');
        Livewire::test(CreateProject::class)->assertFormFieldExists('title')->assertFormFieldDoesNotExist('slug');
    }

    public function test_the_slug_is_made_from_the_title_or_name(): void
    {
        $this->signInTo($this->plumbing);

        $this->assertSame('leak-repair', Service::create(['name' => 'Leak Repair'])->slug);
        $this->assertSame('sector-70-mohali', ServiceArea::create(['name' => 'Sector 70, Mohali'])->slug);
        $this->assertSame('new-bathroom-fit-out', Project::create(['title' => 'New bathroom fit-out'])->slug);
        $this->assertSame('winter-care-tips', Post::create(['title' => 'Winter care tips'])->slug);
        $this->assertSame('our-story', Page::create(['title' => 'Our Story'])->slug);

        // A second one in the same store and template gets the next free address.
        $this->assertSame('leak-repair-2', Service::create(['name' => 'Leak repair'])->slug);
    }

    public function test_each_template_may_have_its_own_record_with_a_slug(): void
    {
        // Platform content (no store signed in), kept per template: each design has its own
        // "About" page at /about.
        $fashion = Page::withoutGlobalScopes()->create(['client_id' => null, 'storefront_template' => 'fashion', 'title' => 'About']);
        $grocery = Page::withoutGlobalScopes()->create(['client_id' => null, 'storefront_template' => 'grocery', 'title' => 'About']);
        $again = Page::withoutGlobalScopes()->create(['client_id' => null, 'storefront_template' => 'fashion', 'title' => 'About']);

        $this->assertSame(['about', 'about', 'about-2'], [$fashion->slug, $grocery->slug, $again->slug]);
    }

    public function test_a_page_never_takes_an_address_another_route_answers(): void
    {
        $this->signInTo($this->fashion);

        // /shop is the shop itself, so a page called "Shop" could never be opened there.
        $this->assertSame('shop-2', Page::create(['title' => 'Shop'])->slug);
        $this->assertSame('cart-2', Page::create(['title' => 'Cart'])->slug);
        $this->assertSame('size-guide', Page::create(['title' => 'Size guide'])->slug);
    }

    public function test_renaming_a_page_keeps_its_address(): void
    {
        $this->signInTo($this->fashion);
        $page = Page::create(['title' => 'Our Story']);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSee('Page address: /our-story')
            ->fillForm(['title' => 'About us'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('our-story', $page->fresh()->slug);
    }
}
