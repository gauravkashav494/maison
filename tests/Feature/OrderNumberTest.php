<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Pages\SiteSettings;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Order numbers: the store's own prefix, the date and a random code without look-alike
 * characters (EFS-261123-K7QM), unique within the store.
 */
class OrderNumberTest extends TestCase
{
    use RefreshDatabase;

    /** Four characters from the alphabet: no 0, O, 1, I or L. */
    private const CODE = '[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{4}';

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Ethnic Fashion Store', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $this->checkoutSettings([]);
    }

    private function checkoutSettings(array $changes): void
    {
        Setting::updateOrCreate(['key' => 'client:'.$this->client->id.':checkout'], ['value' => $changes + [
            'shipping_methods' => [['code' => 'standard', 'name' => 'Standard', 'cost' => 0]],
        ] + config('client_defaults.checkout')]);
        Cache::forget('settings.all');
    }

    private function placeOrder(): Order
    {
        $product = Product::withoutGlobalScopes()->firstOrCreate(['client_id' => $this->client->id, 'slug' => 'linen-shirt'], [
            'template' => 'fashion', 'name' => 'Linen shirt', 'price' => 1250, 'stock' => 50, 'is_active' => true,
        ]);
        $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', ['product_id' => $product->id, 'qty' => 1])->assertSuccessful();
        $items = session('cart.items');
        $this->flushSession();

        $customer = Customer::withoutGlobalScopes()->firstOrCreate(['client_id' => $this->client->id, 'email' => 'asha@example.test'], ['name' => 'Asha Rao', 'password' => 'password123', 'is_active' => true]);
        $this->actingAs($customer, 'web')
            ->withSession([Client::SESSION_KEY => 'alpha', 'cart.items' => $items])
            ->post('/checkout', [
                'email' => 'asha@example.test', 'phone' => '9876543210', 'shipping_name' => 'Asha Rao',
                'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai', 'shipping_state' => 'MH',
                'shipping_postal_code' => '400001', 'shipping_country' => 'India',
                'shipping_method' => 'standard', 'payment_method' => 'cod',
            ])->assertRedirect();

        return Order::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    public static function names(): array
    {
        return [
            'three words' => ['Ethnic Fashion Store', 'EFS'],
            'more than three' => ['Maison Élan Fine Fashion', 'MEF'],
            'two words' => ['Bulk Order', 'BO'],
            'one word' => ['Lumen', 'LUM'],
            'punctuation and accents' => ['Café & Co.', 'CC'],
            'digits' => ['24 Seven Mart', '2SM'],
            'nothing usable' => ['', 'ORD'],
            'too short' => ['X', 'ORD'],
        ];
    }

    #[DataProvider('names')]
    public function test_the_default_prefix_comes_from_the_store_name(string $name, string $prefix): void
    {
        $this->assertSame($prefix, Order::defaultNumberPrefix($name));
    }

    public function test_an_order_is_numbered_with_the_stores_prefix_the_date_and_a_clear_code(): void
    {
        $order = $this->placeOrder();

        $this->assertMatchesRegularExpression('/^EFS-'.now()->format('ymd').'-'.self::CODE.'$/', $order->number);
    }

    public function test_the_store_can_choose_its_own_prefix(): void
    {
        $this->checkoutSettings(['order_prefix' => 'ef']);

        $order = $this->placeOrder();

        $this->assertMatchesRegularExpression('/^EF-\d{6}-'.self::CODE.'$/', $order->number);
    }

    public function test_numbers_never_repeat_within_a_store(): void
    {
        $numbers = collect(range(1, 5))->map(fn () => $this->placeOrder()->number);

        $this->assertCount(5, $numbers->unique());
    }

    public function test_the_tracking_form_shows_the_stores_format(): void
    {
        $this->withSession([Client::SESSION_KEY => 'alpha'])->get('/track-order')
            ->assertOk()
            ->assertSee('EFS-'.now()->format('ymd').'-XXXX', false)
            ->assertDontSee('ME-260916', false);
    }

    public function test_the_prefix_is_set_in_site_settings_and_checked(): void
    {
        $admin = ClientUser::create([
            'client_id' => $this->client->id, 'name' => 'Priya Sharma', 'email' => 'priya@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, $this->client->templateId());

        Livewire::test(SiteSettings::class)
            ->set('data.checkout.order_prefix', 'efs-1')
            ->call('save')
            ->assertHasErrors(['data.checkout.order_prefix']);

        Livewire::test(SiteSettings::class)
            ->set('data.checkout.order_prefix', 'ef26')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('EF26', Setting::forClient($this->client->id, 'checkout')['order_prefix']);
    }
}
