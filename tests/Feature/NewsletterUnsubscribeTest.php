<?php

namespace Tests\Feature;

use App\Filament\Resources\Subscribers\Pages\ListSubscribers;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Models\Subscriber;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Newsletter subscribers leave through their own link (confirm page, then a POST), signing up
 * again opts them back in, a link only works on its own client's store, and the admin exports
 * the list with each person's link.
 */
class NewsletterUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    private Client $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $this->beta = Client::create(['name' => 'Beta', 'subdomain' => 'beta', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function onStore(string $subdomain = 'alpha'): static
    {
        return $this->withSession([Client::SESSION_KEY => $subdomain]);
    }

    private function subscribe(string $email, string $subdomain = 'alpha'): Subscriber
    {
        $this->onStore($subdomain)->postJson('/newsletter', ['email' => $email, 'source' => 'footer'])->assertOk();

        return Subscriber::withoutGlobalScopes()->where('email', strtolower($email))->firstOrFail();
    }

    public function test_every_new_subscriber_gets_a_link_on_their_stores_host(): void
    {
        $subscriber = $this->subscribe('riya@example.com');

        $this->assertSame(40, strlen($subscriber->unsubscribe_token));
        $this->assertSame($this->alpha->publicUrl().'/newsletter/unsubscribe/'.$subscriber->unsubscribe_token, $subscriber->unsubscribeUrl());
        $this->assertArrayNotHasKey('unsubscribe_token', $subscriber->toArray());
    }

    public function test_opening_the_link_only_asks_and_the_button_unsubscribes(): void
    {
        $subscriber = $this->subscribe('riya@example.com');
        $url = '/newsletter/unsubscribe/'.$subscriber->unsubscribe_token;

        $this->onStore()->get($url)->assertOk()->assertSee('Unsubscribe from our newsletter?')->assertSee('riya@example.com');
        $this->assertNull($subscriber->fresh()->unsubscribed_at, 'a GET (mail scanner, link preview) must not unsubscribe');

        $this->onStore()->post($url)->assertRedirect($url);
        $this->assertNotNull($subscriber->fresh()->unsubscribed_at);

        $this->onStore()->get($url)->assertOk()->assertSee('You’re unsubscribed');
    }

    public function test_signing_up_again_opts_back_in_with_the_same_row(): void
    {
        $subscriber = $this->subscribe('riya@example.com');
        $this->onStore()->post('/newsletter/unsubscribe/'.$subscriber->unsubscribe_token);

        $again = $this->subscribe('Riya@Example.com');

        $this->assertSame($subscriber->id, $again->id);
        $this->assertNull($again->unsubscribed_at);
    }

    public function test_a_link_does_not_work_on_another_clients_store(): void
    {
        $subscriber = $this->subscribe('riya@example.com', 'alpha');
        $url = '/newsletter/unsubscribe/'.$subscriber->unsubscribe_token;

        $this->onStore('beta')->get($url)->assertOk()->assertSee('This link has expired')->assertDontSee('riya@example.com');
        $this->onStore('beta')->post($url);

        $this->assertNull($subscriber->fresh()->unsubscribed_at);
    }

    public function test_an_unknown_token_shows_the_expired_message(): void
    {
        $this->onStore()->get('/newsletter/unsubscribe/'.str_repeat('x', 40))->assertOk()->assertSee('This link has expired');
    }

    public function test_the_admin_exports_the_filtered_list_with_each_link(): void
    {
        $kept = $this->subscribe('kept@example.com');
        $gone = $this->subscribe('gone@example.com');
        $this->onStore()->post('/newsletter/unsubscribe/'.$gone->unsubscribe_token);
        $this->subscribe('other@example.com', 'beta');

        $admin = ClientUser::create([
            'client_id' => $this->alpha->id, 'name' => 'Alpha Admin', 'email' => 'admin@alpha.test',
            'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        // The storefront requests above left their client scope on the model (static, per process);
        // a real admin request starts without it.
        Subscriber::clearBootedModels();
        Filament::setCurrentPanel('client');
        $this->actingAs($admin, 'client')->get('/client')->assertOk(); // resolves the client context

        $page = Livewire::test(ListSubscribers::class)
            ->filterTable('status', true)
            ->callAction('export')
            ->assertFileDownloaded('subscribers-'.now()->format('Y-m-d').'.csv');
        $csv = base64_decode(data_get($page->effects, 'download.content'));

        $this->assertStringContainsString('kept@example.com', $csv);
        $this->assertStringContainsString($kept->unsubscribeUrl(), $csv);
        $this->assertStringNotContainsString('gone@example.com', $csv, 'filtered to Subscribed');
        $this->assertStringNotContainsString('other@example.com', $csv, 'another client’s subscriber');
    }

    public function test_formula_like_values_are_neutralised_in_the_export(): void
    {
        $method = new \ReflectionMethod(ListSubscribers::class, 'safeCell');

        $this->assertSame("'=HYPERLINK(\"x\")", $method->invoke(null, '=HYPERLINK("x")'));
        $this->assertSame('footer', $method->invoke(null, 'footer'));
        $this->assertSame('', $method->invoke(null, null));
    }
}
