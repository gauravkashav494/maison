<?php

namespace Tests\Feature;

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
        'grocery attributes' => 'Grocery attributes',
        'nutrition' => 'Dietary, benefits',
        'plumbing tab' => 'Plumbing specifications',
        'max quantity' => 'Max quantity per order',
    ];

    public static function templates(): array
    {
        return [
            'fashion' => ['fashion', []],
            'grocery' => ['grocery', ['grocery tab', 'grocery attributes', 'max quantity']],
            'heritage' => ['heritage', ['grocery tab', 'grocery attributes', 'nutrition', 'max quantity']],
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
}
