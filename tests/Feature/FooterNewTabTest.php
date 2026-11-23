<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Menu;
use App\Models\Plan;
use App\Models\Storefront;
use App\Templates\TemplateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Open in a new tab" on a menu item (Content → Navigation) is followed by every footer of every
 * store: each footer column and the policy links. A link without it opens in the same tab.
 */
class FooterNewTabTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function stores(): array
    {
        return ['fashion' => ['fashion'], 'grocery' => ['grocery'], 'heritage' => ['heritage'], 'plumbing' => ['plumbing'], 'plumbing-services' => ['plumbing-services'], 'bulk' => ['bulk']];
    }

    #[DataProvider('stores')]
    public function test_every_footer_menu_follows_open_in_a_new_tab(string $template): void
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Store', 'slug' => $template, 'template' => $template, 'is_active' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        // One link that opens in a new tab and one that does not, in each footer menu of the store.
        $footers = [];
        foreach (app(TemplateManager::class)->get($template)->menuLocations() as $alias => [$location]) {
            if ($alias === 'header') {
                continue;
            }
            $menu = Menu::withoutGlobalScopes()->create(['client_id' => $client->id, 'template' => $template, 'name' => $alias, 'location' => $location]);
            $menu->allItems()->create(['client_id' => $client->id, 'label' => 'External '.$alias, 'url' => 'https://example.com/'.$alias, 'opens_in_new_tab' => true, 'sort_order' => 0]);
            $menu->allItems()->create(['client_id' => $client->id, 'label' => 'Inside '.$alias, 'url' => '/inside-'.$alias, 'opens_in_new_tab' => false, 'sort_order' => 1]);
            $footers[] = $alias;
        }
        Cache::flush();

        $html = $this->withSession([Client::SESSION_KEY => 'alpha'])->get($template === 'plumbing-services' ? '/services' : '/shop')->assertOk()->getContent();

        $this->assertNotEmpty($footers);
        foreach ($footers as $alias) {
            $this->assertMatchesRegularExpression('#<a href="https://example.com/'.$alias.'"\s+target="_blank" rel="noopener"#', $html, "{$template} {$alias}: a new-tab link opens in a new tab");
            $this->assertMatchesRegularExpression('#<a href="/inside-'.$alias.'"\s+(?!target)#', $html, "{$template} {$alias}: other links stay in the same tab");
        }
    }

    public function test_the_heritage_footer_shows_its_shop_menu_only_when_it_has_links(): void
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Store', 'slug' => 'heritage', 'template' => 'heritage', 'is_active' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $visit = fn () => $this->withSession([Client::SESSION_KEY => 'alpha'])->get('/shop')->assertOk();

        $visit()->assertDontSee('data-menu-area="footer_shop"', false);

        $menu = Menu::withoutGlobalScopes()->create(['client_id' => $client->id, 'template' => 'heritage', 'name' => 'Shop', 'location' => 'heritage_footer_shop']);
        $menu->allItems()->create(['client_id' => $client->id, 'label' => 'Rice & Grains', 'url' => '/shop/rice-grains', 'sort_order' => 0]);
        Cache::flush();

        $visit()->assertSee('data-menu-area="footer_shop"', false)->assertSee('Rice &amp; Grains', false);
    }
}
