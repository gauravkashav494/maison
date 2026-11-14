<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Page;
use App\Models\Plan;
use App\Models\Storefront;
use Database\Seeders\ContentSeeder;
use Database\Seeders\PagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pages: the layout list holds only what the store's design can show, a slug is used once per
 * store (a shared page and the store's own one can't compete for the same URL), and setting up
 * a Fashion store no longer creates hidden duplicate pages.
 */
class PageLayoutsTest extends TestCase
{
    use RefreshDatabase;

    private function storeAdmin(string $template): ClientUser
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => ucfirst($template), 'slug' => $template, 'template' => $template, 'is_active' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        return ClientUser::create([
            'client_id' => $client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
    }

    /**
     * Fill in and submit the "New page" form the way the browser does (one Livewire update), and
     * return the validation errors. Filament's form test helpers don't run in this test setup.
     */
    private function submitCreateForm(ClientUser $admin, array $data): array
    {
        $html = $this->actingAs($admin, 'client')->get('/client/pages/create')->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $m);
        $snapshot = collect($m[1])->map(fn ($s) => html_entity_decode($s))
            ->first(fn ($s) => str_contains($s, '"data"') && str_contains($s, 'storefront_template'));
        $this->assertNotNull($snapshot, 'create form not found');

        $updates = collect($data)->mapWithKeys(fn ($v, $k) => ['data.'.$k => $v])->all();
        $response = $this->actingAs($admin, 'client')->withHeaders(['X-Livewire' => '1'])
            ->postJson($this->app['livewire']->getUpdateUri(), ['_token' => csrf_token(), 'components' => [[
                'snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['path' => '', 'method' => 'create', 'params' => []]],
            ]]])->assertOk();

        $memo = json_decode($response->json('components.0.snapshot'), true)['memo'] ?? [];

        return $memo['errors'] ?? [];
    }

    public static function designs(): array
    {
        return [
            'fashion has every layout' => ['fashion', ['Editorial page', 'Size guide (tables)', 'Store locator', 'Gift cards'], []],
            'plumbing services has six' => ['plumbing-services', ['Editorial page', 'Careers (open roles)', 'Contact (form + details)'], ['Size guide (tables)', 'Care guide (by material)', 'Gift cards', 'Store locator']],
            'bulk order has contact, editorial and policy' => ['bulk', ['Contact (form + details)', 'Editorial page', 'policy document'], ['Size guide (tables)', 'Gift cards', 'About (brand story sections)']],
        ];
    }

    #[DataProvider('designs')]
    public function test_the_layout_list_offers_only_what_the_design_can_show(string $template, array $offered, array $notOffered): void
    {
        $html = $this->actingAs($this->storeAdmin($template), 'client')->get('/client/pages/create')->assertOk()->getContent();

        foreach ($offered as $label) {
            $this->assertStringContainsString($label, $html, "{$template} should offer {$label}");
        }
        foreach ($notOffered as $label) {
            $this->assertStringNotContainsString($label, $html, "{$template} should not offer {$label}");
        }
    }

    public function test_a_slug_is_used_once_per_store_even_across_shared_and_own_pages(): void
    {
        $admin = $this->storeAdmin('fashion');
        // A shared page (no template) that the store already shows at /about.
        Page::create(['client_id' => $admin->client_id, 'title' => 'Our Story', 'slug' => 'about', 'template' => 'default', 'storefront_template' => null, 'is_active' => true]);

        $errors = $this->submitCreateForm($admin, ['title' => 'About us', 'slug' => 'about', 'template' => 'about']);

        $this->assertArrayHasKey('data.slug', $errors);
        $this->assertSame(1, Page::withoutGlobalScopes()->where('slug', 'about')->count());
    }

    public function test_setting_up_a_fashion_store_creates_no_hidden_duplicate_pages(): void
    {
        app(ContentSeeder::class)->run();
        app(PagesSeeder::class)->run();

        $this->assertSame(0, Page::withoutGlobalScopes()->whereNull('storefront_template')->count());
        $this->assertSame(16, Page::withoutGlobalScopes()->where('storefront_template', 'fashion')->count());
    }
}
