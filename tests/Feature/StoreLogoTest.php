<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Pages\FashionSettings;
use App\Filament\Pages\SiteSettings;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlatformActivity;
use App\Models\Setting;
use App\Models\Storefront;
use App\Support\Invoice;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One logo per store, uploaded in Site settings → Brand: every template's header, menu, footer
 * and sign-in page show it in place of the text wordmark, and invoices print it. Without one,
 * everything shows the wordmark as before.
 */
class StoreLogoTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private function storeOn(string $template, string $subdomain = 'alpha'): Client
    {
        Storage::fake('public');
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        Storefront::syncWithTemplates();

        $this->client = Client::create([
            'name' => 'Alpha Home', 'subdomain' => $subdomain,
            'storefront_id' => Storefront::where('template', $template)->firstOrFail()->id, 'plan_id' => $plan->id, 'is_active' => true,
        ]);
        app(ClientContext::class)->resolveForPublic($this->client);

        return $this->client;
    }

    private function signInOwner(): ClientUser
    {
        $owner = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Owner', 'email' => 'owner@alpha.test', 'password' => 'secret-pass',
            'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($owner, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($owner);
        app(StoreContext::class)->resolve($owner, app(ClientContext::class)->templateId());

        return $owner;
    }

    /** A logo file on the public disk, set as the store's logo. */
    private function setLogo(string $path = 'clients/1/branding/logo.png'): string
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image('logo.png', 400, 120)->getContent());
        Setting::mergeForClient($this->client, 'site', ['logo' => $path]);

        return $path;
    }

    public function test_the_logo_is_uploaded_in_site_settings_and_the_change_is_logged(): void
    {
        $this->storeOn('fashion');
        $this->signInOwner();

        Livewire::test(SiteSettings::class)
            ->set('data.site.logo', UploadedFile::fake()->image('alpha-logo.png', 400, 120))
            ->call('save')
            ->assertHasNoErrors();

        $path = Setting::get('site.logo');
        $this->assertIsString($path);
        $this->assertStringStartsWith($this->client->mediaDirectory().'/branding/', $path);
        Storage::disk('public')->assertExists($path);

        $line = PlatformActivity::latest('id')->firstOrFail();
        $this->assertSame('updated', $line->action);
        $this->assertSame($this->client->id, $line->client_id);
        $this->assertContains('site.logo', array_column($line->changes, 'field'));

        // Fashion keeps its wordmark in the same "site" group: saving that page keeps the logo.
        Livewire::test(FashionSettings::class)->set('data.site.logo_primary', 'ALPHA')->call('save')->assertHasNoErrors();
        $this->assertSame($path, Setting::get('site.logo'));
    }

    public function test_the_logo_must_be_an_image(): void
    {
        $this->storeOn('fashion');
        $this->signInOwner();

        Livewire::test(SiteSettings::class)
            ->set('data.site.logo', UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasErrors();

        $this->assertNull(Setting::get('site.logo'));
    }

    /** @return array<string, array{string}> */
    public static function templates(): array
    {
        return [
            'fashion' => ['fashion'],
            'grocery' => ['grocery'],
            'heritage' => ['heritage'],
            'plumbing' => ['plumbing'],
            'plumbing services' => ['plumbing-services'],
            'bulk' => ['bulk'],
        ];
    }

    #[DataProvider('templates')]
    public function test_every_template_shows_the_logo_in_place_of_its_wordmark(string $template): void
    {
        $this->storeOn($template);
        // The store's own address: a bare /login is the platform's sign-in page.
        $page = fn () => $this->get($this->client->publicUrl().'/login')->assertOk();

        // No logo: the text wordmark, no logo image.
        $page()->assertDontSee('/branding/logo.png', false);

        $path = $this->setLogo();
        $html = $page()->getContent();

        $url = Storage::disk('public')->url($path);
        // Header, footer and the sign-in panel at least (mobile menus add more).
        $this->assertGreaterThanOrEqual(3, substr_count($html, 'src="'.e($url).'"'), 'The logo shows in each place the wordmark did.');
        $this->assertStringContainsString('alt="'.e(setting('site.name')).'"', $html);
    }

    public function test_the_invoice_prints_the_logo_and_keeps_it_with_the_order(): void
    {
        $this->storeOn('fashion');
        $this->setLogo();

        $seller = Invoice::sellerDetails();
        $this->assertSame('clients/1/branding/logo.png', $seller['logo']);

        $order = Order::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'number' => 'AL-1', 'status' => 'confirmed', 'email' => 'a@b.test', 'phone' => '9999999999',
            'shipping_name' => 'Asha', 'shipping_line1' => '1 Road', 'shipping_city' => 'Pune', 'shipping_state' => 'MH',
            'shipping_postal_code' => '411001', 'shipping_country' => 'India', 'shipping_method' => 'Standard',
            'payment_method' => 'cod', 'payment_status' => 'cod', 'subtotal' => 100, 'discount' => 0, 'shipping_cost' => 0,
            'tax' => 0, 'total' => 100, 'seller' => $seller, 'dispatched_at' => now(),
        ]);

        $data = Invoice::for($order)->data();
        $this->assertStringStartsWith('data:image/png;base64,', $data['logo']);
        $html = view('invoices.order', $data)->render();
        $this->assertStringContainsString('<img class="store-logo" src="data:image/png;base64,', $html);
        // The logo takes the place of the separate store-name line.
        $this->assertStringNotContainsString('store-brand">', $html);

        // A logo file removed later: the invoice prints without it, never a broken image.
        Storage::disk('public')->delete('clients/1/branding/logo.png');
        $this->assertNull(Invoice::for($order)->data()['logo']);
        $this->assertStringNotContainsString('store-logo"', view('invoices.order', Invoice::for($order)->data())->render());
    }
}
