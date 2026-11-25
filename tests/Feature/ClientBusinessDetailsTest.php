<?php

namespace Tests\Feature;

use App\Clients\ClientSetup;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\PlatformActivity;
use App\Models\Setting;
use App\Models\Storefront;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Creating a client starts with the business behind the store. The answers go into the new
 * store's own settings (invoice seller details and contact details), on top of its defaults.
 */
class ClientBusinessDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Storefront $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(ClientSetup::class, fn ($mock) => $mock->shouldReceive('startInBackground'));
        Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $this->store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);

        $this->actingAs(User::create([
            'name' => 'Operator', 'email' => 'ops@platform.test', 'password' => 'password123',
            'role' => User::ROLE_SUPER_ADMIN, 'is_admin' => true, 'is_active' => true,
        ]), 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function create(array $business)
    {
        return Livewire::test(CreateClient::class)
            ->fillForm([
                'business' => $business,
                'name' => 'Delta', 'subdomain' => 'delta', 'storefront_id' => $this->store->id,
                'admin' => ['name' => 'Delta Owner', 'email' => 'owner@delta.test', 'password' => 'secret-pass1'],
            ])
            ->call('create');
    }

    public function test_business_details_open_the_first_step_with_the_client_and_store(): void
    {
        $steps = (new CreateClient)->getSteps();

        $this->assertSame('Business & store', $steps[0]->getLabel());
        $this->assertSame('Plan & limits', $steps[1]->getLabel(), 'No separate business step any more.');

        // One step: the business details, then the client and its store below them.
        Livewire::test(CreateClient::class)
            ->assertSeeInOrder(['Business & store', 'Plan & limits', 'Registered business name', 'GSTIN', 'Subdomain', 'Store']);
    }

    public function test_the_details_fill_the_new_stores_settings_and_keep_its_defaults(): void
    {
        $this->create([
            'business_name' => 'Delta Traders Pvt Ltd', 'contact_person' => 'Meena Kaur',
            'contact_email' => 'accounts@delta.test', 'contact_phone' => '+91 98765 43210',
            'gstin' => '03aaaca1234a1z5', 'pan' => 'aaaca1234a', 'state' => '03',
            'address' => "12 Mall Road\nLudhiana 141001",
        ])->assertHasNoFormErrors();

        $client = Client::where('subdomain', 'delta')->sole();
        $checkout = Setting::forClient($client->id, 'checkout');
        $site = Setting::forClient($client->id, 'site');

        $this->assertSame('Delta Traders Pvt Ltd', $checkout['invoice_business_name']);
        $this->assertSame('03AAACA1234A1Z5', $checkout['invoice_gstin']);
        $this->assertSame('AAACA1234A', $checkout['invoice_pan']);
        $this->assertSame('03', $checkout['invoice_state']);
        $this->assertSame("12 Mall Road\nLudhiana 141001", $checkout['invoice_address']);
        $this->assertSame('Meena Kaur', $site['contact_person']);
        $this->assertSame('accounts@delta.test', $site['contact_email']);
        $this->assertNotEmpty($site['contact_phone']);

        // The rest of each group is the store's defaults, with its own name filled in.
        $this->assertSame(config('client_defaults.checkout.shipping_methods'), $checkout['shipping_methods']);
        $this->assertSame('Delta', $site['name']);
    }

    public function test_only_the_business_name_is_required(): void
    {
        $this->create([])->assertHasFormErrors(['business.business_name' => 'required']);

        $this->create(['business_name' => 'Delta Traders'])->assertHasNoFormErrors();
        $client = Client::where('subdomain', 'delta')->sole();
        $this->assertSame('Delta Traders', Setting::forClient($client->id, 'checkout')['invoice_business_name']);
        $this->assertArrayNotHasKey('invoice_gstin', Setting::forClient($client->id, 'checkout'));
    }

    public function test_a_wrong_gstin_or_pan_is_refused(): void
    {
        $this->create(['business_name' => 'Delta', 'gstin' => '12345'])->assertHasFormErrors(['business.gstin']);
        $this->create(['business_name' => 'Delta', 'pan' => 'ABC'])->assertHasFormErrors(['business.pan']);
        // A GSTIN carries the PAN: the two must agree.
        $this->create(['business_name' => 'Delta', 'gstin' => '03AAACA1234A1Z5', 'pan' => 'BBBBB1234B'])->assertHasFormErrors(['business.pan']);

        $this->assertDatabaseMissing('clients', ['subdomain' => 'delta']);
    }

    public function test_the_gstin_fills_in_the_pan_and_state(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm(['business' => ['business_name' => 'Delta', 'gstin' => '03AAACA1234A1Z5']])
            ->assertSchemaStateSet(['business.pan' => 'AAACA1234A', 'business.state' => '03']);
    }

    public function test_the_business_name_starts_the_client_name_and_subdomain(): void
    {
        Livewire::test(CreateClient::class)
            ->fillForm(['business' => ['business_name' => 'Delta Traders']])
            ->assertSchemaStateSet(['name' => 'Delta Traders', 'subdomain' => 'delta-traders']);
    }

    /** An existing client with an active admin (an edit cannot save without one) and saved details. */
    private function existingClient(): Client
    {
        $client = Client::create(['name' => 'Echo', 'subdomain' => 'echo', 'storefront_id' => $this->store->id, 'is_active' => true]);
        ClientUser::create([
            'client_id' => $client->id, 'name' => 'Echo Owner', 'email' => 'owner@echo.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        Setting::mergeForClient($client, 'checkout', ['invoice_business_name' => 'Echo Traders', 'invoice_gstin' => '03AAACA1234A1Z5', 'invoice_state' => '03']);
        Setting::mergeForClient($client, 'site', ['contact_email' => 'hello@echo.test']);

        return $client;
    }

    public function test_the_edit_form_shows_the_saved_details_first(): void
    {
        $client = $this->existingClient();

        $page = Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->assertSet('data.business.business_name', 'Echo Traders')
            ->assertSet('data.business.gstin', '03AAACA1234A1Z5')
            ->assertSet('data.business.contact_email', 'hello@echo.test')
            ->assertSet('data.business.pan', null);

        $this->assertSame('Business & store', $page->instance()->getSteps()[0]->getLabel());
    }

    public function test_editing_saves_the_details_and_logs_what_changed(): void
    {
        $client = $this->existingClient();

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->set('data.business.pan', 'aaaca1234a')
            ->set('data.business.contact_email', '')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('AAACA1234A', Setting::forClient($client->id, 'checkout')['invoice_pan']);
        $this->assertSame('Echo Traders', Setting::forClient($client->id, 'checkout')['invoice_business_name']);
        $this->assertNull(Setting::forClient($client->id, 'site')['contact_email'], 'An emptied field is cleared.');

        $line = PlatformActivity::where('description', 'like', '%business details%')->sole();
        $this->assertSame('updated', $line->action);
        $this->assertSame('updated the business details of client "Echo": business email and PAN', $line->description);
        $this->assertSame($client->id, $line->client_id);
    }

    public function test_saving_without_changing_the_details_writes_no_line(): void
    {
        $client = $this->existingClient();

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])->call('save')->assertHasNoFormErrors();

        $this->assertSame(0, PlatformActivity::where('description', 'like', '%business details%')->count());
    }

    public function test_an_older_client_without_a_business_name_can_still_be_saved(): void
    {
        $client = Client::create(['name' => 'Old', 'subdomain' => 'old', 'storefront_id' => $this->store->id, 'is_active' => true]);
        ClientUser::create([
            'client_id' => $client->id, 'name' => 'Old Owner', 'email' => 'owner@old.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])->call('save')->assertHasNoFormErrors();
    }

    public function test_the_detail_page_shows_the_business_details(): void
    {
        $client = $this->existingClient();

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->assertSee('Business details')
            ->assertSee('Echo Traders')
            ->assertSee('03AAACA1234A1Z5')
            ->assertSee('hello@echo.test')
            ->assertSee('Punjab (03)');
    }
}
