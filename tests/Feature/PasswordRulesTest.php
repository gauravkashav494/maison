<?php

namespace Tests\Feature;

use App\Clients\ClientContext;
use App\Filament\Resources\ClientUsers\Pages\CreateClientUser;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Support\PasswordRules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One password rule for every account (PasswordRules), and the live checklist under each
 * new-password field is built from that same rule.
 */
class PasswordRulesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private ClientUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::firstOrCreate(['slug' => 'fashion'], ['name' => 'Fashion Store', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Acme', 'subdomain' => 'acme', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => 'secret-pass1', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
    }

    /** @return array<string, array{string, bool}> */
    public static function passwords(): array
    {
        return [
            'too short' => ['abc123', false],
            'letters only' => ['onlyletters', false],
            'numbers only' => ['12345678', false],
            'letter and number' => ['secret-pass1', true],
            'non-latin letter counts' => ['पासवर्ड12345', true],
        ];
    }

    /** @dataProvider passwords */
    public function test_the_rule_and_every_checklist_item_agree(string $password, bool $valid): void
    {
        $this->assertSame($valid, Validator::make(['p' => $password], ['p' => PasswordRules::rule()])->passes());
        // Password::defaults() (Filament's own pages) is the same rule.
        $this->assertSame($valid, Validator::make(['p' => $password], ['p' => Password::default()])->passes());

        // The checklist ticks every item exactly when the server accepts the password. The
        // patterns are JavaScript; these ones read the same in PCRE with the `u` flag.
        $allTicked = collect(PasswordRules::checks())->every(fn (array $check) => preg_match('/'.$check['pattern'].'/u', $password) === 1);
        $this->assertSame($valid, $allTicked);
    }

    public function test_the_storefront_refuses_a_password_without_a_number_and_shows_the_checklist(): void
    {
        $this->withSession([Client::SESSION_KEY => 'acme'])->get('/register')
            ->assertSuccessful()
            ->assertSee('data-password-checklist', false)
            ->assertSee('8+ characters')
            ->assertSee('a number')
            ->assertSee('data-password-match', false);

        $this->withSession([Client::SESSION_KEY => 'acme'])->post('/register', [
            'name' => 'Shopper', 'email' => 'shopper@acme.test', 'phone' => '9876543210',
            'password' => 'onlyletters', 'password_confirmation' => 'onlyletters',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_panel_password_field_uses_the_same_rule_and_shows_the_checklist(): void
    {
        $this->actingAs($this->admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($this->admin);

        Livewire::test(CreateClientUser::class)
            ->assertSee('data-password-checklist', false)
            ->fillForm(['name' => 'Staff', 'email' => 'staff@acme.test', 'password' => 'onlyletters', 'role' => ClientUser::ROLE_ADMIN_STAFF, 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['password']);

        Livewire::test(CreateClientUser::class)
            ->fillForm(['name' => 'Staff', 'email' => 'staff@acme.test', 'password' => 'staff-pass1', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();
    }
}
