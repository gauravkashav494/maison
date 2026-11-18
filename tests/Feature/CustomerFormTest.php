<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** A customer added in the admin needs a phone number; the email is optional and comes after it. */
class CustomerFormTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    public function test_a_customer_can_be_added_with_a_phone_and_no_email(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Asha', 'phone' => '+91 98765 43210', 'email' => null])
            ->call('create')
            ->assertHasNoFormErrors();

        $customer = Customer::where('client_id', $this->client->id)->where('name', 'Asha')->firstOrFail();
        $this->assertNull($customer->email);
        $this->assertNotNull($customer->phone);
        $this->assertNull($customer->routeNotificationForMail());

        // A second phone-only customer of the same store is fine too.
        Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Ravi', 'phone' => '+91 91234 56789'])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_the_phone_is_required(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Asha', 'phone' => null, 'email' => 'asha@example.test'])
            ->call('create')
            ->assertHasFormErrors(['phone' => 'required']);
    }

    public function test_an_email_given_is_still_checked_and_unique_in_the_store(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Old', 'email' => 'asha@example.test', 'phone' => '+919000000000', 'is_active' => true]);

        Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Asha', 'phone' => '+91 98765 43210', 'email' => 'not-an-email'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'email']);

        Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Asha', 'phone' => '+91 98765 43210', 'email' => 'asha@example.test'])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_the_email_field_comes_right_after_the_phone(): void
    {
        $fields = array_keys(Livewire::test(CreateCustomer::class)->instance()->getSchema('form')->getFlatFields());

        $this->assertSame(array_search('phone', $fields, true) + 1, array_search('email', $fields, true));
    }
}
