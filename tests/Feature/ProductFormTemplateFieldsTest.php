<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The product form shows only the field groups the store's template displays
 * (Template::productFields()): a fashion store is not asked for nutrition or pipe specs.
 */
class ProductFormTemplateFieldsTest extends TestCase
{
    use RefreshDatabase;

    private const FIELDS = [
        'grocery tab' => 'Grocery &amp; nutrition',
        'grocery details' => 'Grocery details',
        'nutrition' => 'Dietary, benefits',
        'plumbing tab' => 'Plumbing specifications',
        'max quantity' => 'Max quantity per order',
    ];

    public static function templates(): array
    {
        return [
            'fashion' => ['fashion', []],
            'grocery' => ['grocery', ['grocery tab', 'grocery details', 'max quantity']],
            'heritage' => ['heritage', ['grocery tab', 'grocery details', 'nutrition', 'max quantity']],
            'plumbing' => ['plumbing', ['plumbing tab', 'max quantity']],
            'bulk' => ['bulk', ['max quantity']],
        ];
    }

    #[DataProvider('templates')]
    public function test_the_form_offers_only_the_fields_the_template_uses(string $template, array $expected): void
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => ucfirst($template), 'slug' => $template, 'template' => $template, 'is_active' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $admin = ClientUser::create([
            'client_id' => $client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        $html = $this->actingAs($admin, 'client')->get('/client/products/create')->assertSuccessful()->getContent();

        foreach (self::FIELDS as $name => $text) {
            in_array($name, $expected, true)
                ? $this->assertStringContainsString($text, $html, "{$template} should show {$name}")
                : $this->assertStringNotContainsString($text, $html, "{$template} should not show {$name}");
        }
    }

    public static function optionPresets(): array
    {
        return [
            'fashion' => ['fashion', ['Size' => 'Size', 'Colour' => 'Colour', 'Style' => 'Style / fit', 'Pack size' => 'Pack size']],
            'grocery' => ['grocery', ['Size' => 'Pack size (weight / volume)', 'Flavour' => 'Flavour', 'Pack size' => 'Pack of (count)']],
            'heritage' => ['heritage', ['Size' => 'Pack size (weight / volume)', 'Flavour' => 'Flavour', 'Pack size' => 'Pack of (count)']],
            'plumbing' => ['plumbing', ['Size' => 'Size (inch / mm)', 'Finish' => 'Finish', 'Colour' => 'Colour', 'Length' => 'Length', 'Pack size' => 'Pack of (count)']],
            'bulk' => ['bulk', ['Size' => 'Size / pack', 'Colour' => 'Colour', 'Finish' => 'Finish', 'Pack size' => 'Pack of (count)']],
        ];
    }

    /** Each store is offered the options its products vary by; Material is never one of them. */
    #[DataProvider('optionPresets')]
    public function test_the_option_dropdown_offers_the_stores_own_options(string $template, array $expected): void
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => ucfirst($template), 'slug' => $template, 'template' => $template, 'is_active' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $admin = ClientUser::create([
            'client_id' => $client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        app(StoreContext::class)->resolve($admin);

        $presets = ProductForm::optionPresets();
        $this->assertSame($expected, $presets);
        $this->assertArrayNotHasKey('Material', $presets);
    }

    public static function optionExamples(): array
    {
        return [
            'fashion' => ['fashion', 'Size: S, M, L'],
            'grocery' => ['grocery', 'Pack size: 250 g, 500 g, 1 kg — Flavour: Masala'],
            'heritage' => ['heritage', 'Flavour: Kesar, Elaichi'],
            'plumbing' => ['plumbing', 'Size: 1/2 inch, 3/4 inch, 1 inch'],
            'bulk' => ['bulk', 'Size / pack: 500 ml, 1 L, 5 L'],
        ];
    }

    #[DataProvider('optionExamples')]
    public function test_the_options_example_is_in_the_stores_own_terms(string $template, string $example): void
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => ucfirst($template), 'slug' => $template, 'template' => $template, 'is_active' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $admin = ClientUser::create([
            'client_id' => $client->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        $html = $this->actingAs($admin, 'client')->get('/client/products/create')->assertSuccessful()->getContent();
        $this->assertStringContainsString(e($example), $html);
        if ($template !== 'fashion') {
            $this->assertStringNotContainsString('Size: S, M, L', $html, 'No clothing example outside fashion.');
        }
    }
}
