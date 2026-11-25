<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
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
            ->fillForm(['name' => 'Asha', 'phone' => '+91 98765 43210', 'email' => 'Asha@Example.test'])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    public function test_the_email_field_comes_right_after_the_phone(): void
    {
        $fields = array_keys(Livewire::test(CreateCustomer::class)->instance()->getSchema('form')->getFlatFields());

        $this->assertSame(array_search('phone', $fields, true) + 1, array_search('email', $fields, true));
    }

    // ---- One customer per phone and per email, within the store ------------------------

    public function test_the_phone_is_unique_in_the_store_in_any_saved_shape(): void
    {
        // Saved long ago in another shape; also a guest record (no password) counts.
        Customer::create(['client_id' => $this->client->id, 'name' => 'Old', 'phone' => '9876543210', 'is_active' => true]);

        foreach (['+91 98765 43210', '98765 43210', '09876543210'] as $same) {
            Livewire::test(CreateCustomer::class)
                ->fillForm(['name' => 'Asha', 'phone' => $same])
                ->call('create')
                ->assertHasFormErrors(['phone']);
        }
        $this->assertSame(1, Customer::where('client_id', $this->client->id)->count());

        Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Asha', 'phone' => '+91 98765 43211'])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_another_store_may_have_a_customer_with_the_same_phone_and_email(): void
    {
        $other = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $this->client->storefront_id, 'plan_id' => $this->client->plan_id, 'is_active' => true]);
        Customer::create(['client_id' => $other->id, 'name' => 'Asha elsewhere', 'email' => 'asha@example.test', 'phone' => '9876543210', 'is_active' => true]);

        Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Asha', 'phone' => '+91 98765 43210', 'email' => 'asha@example.test'])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_editing_keeps_the_customers_own_phone_and_email_but_not_anothers(): void
    {
        $asha = Customer::create(['client_id' => $this->client->id, 'name' => 'Asha', 'email' => 'asha@example.test', 'phone' => '9876543210', 'is_active' => true]);
        Customer::create(['client_id' => $this->client->id, 'name' => 'Ravi', 'email' => 'ravi@example.test', 'phone' => '9123456789', 'is_active' => true]);

        Livewire::test(EditCustomer::class, ['record' => $asha->getRouteKey()])
            ->fillForm(['name' => 'Asha Rao'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Asha Rao', $asha->fresh()->name);

        Livewire::test(EditCustomer::class, ['record' => $asha->getRouteKey()])
            ->fillForm(['phone' => '+91 91234 56789', 'email' => 'RAVI@example.test'])
            ->call('save')
            ->assertHasFormErrors(['phone', 'email']);
        $this->assertSame('9876543210', $asha->fresh()->phone);
    }

    public function test_a_duplicate_says_which_detail_another_customer_has(): void
    {
        Customer::create(['client_id' => $this->client->id, 'name' => 'Old', 'email' => 'asha@example.test', 'phone' => '9876543210', 'is_active' => true]);

        $page = Livewire::test(CreateCustomer::class)
            ->fillForm(['name' => 'Asha', 'phone' => '+91 98765 43210', 'email' => 'asha@example.test'])
            ->call('create');

        $errors = $page->errors();
        $this->assertSame('Another customer of this store already has this phone number.', $errors->first('data.phone'));
        $this->assertSame('Another customer of this store already has this email address.', $errors->first('data.email'));
    }
}
