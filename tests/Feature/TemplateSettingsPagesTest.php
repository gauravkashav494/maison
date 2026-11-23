<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Auth\Login;
use App\Filament\Pages\BulkOrderSettings;
use App\Filament\Pages\FashionSettings;
use App\Filament\Pages\HeritageSettings;
use App\Filament\Pages\SiteSettings;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Site settings hold what every store shares (name, contact, checkout…); each template's
 * look lives on its own Appearance page. Fashion keeps both in the same "site" group, so the
 * two pages must save without wiping each other's fields.
 */
class TemplateSettingsPagesTest extends TestCase
{
    use RefreshDatabase;

    private function signInOwnerOf(string $template, string $subdomain): Client
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        Storefront::syncWithTemplates();
        $client = Client::create([
            'name' => ucfirst($subdomain).' Demo', 'subdomain' => $subdomain,
            'storefront_id' => Storefront::where('template', $template)->firstOrFail()->id, 'plan_id' => $plan->id, 'is_active' => true,
        ]);
        app(ClientContext::class)->resolveForPublic($client);

        $owner = ClientUser::create([
            'client_id' => $client->id, 'name' => 'Owner', 'email' => "owner@{$subdomain}.test", 'password' => 'secret-pass',
            'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true,
        ]);
        $this->actingAs($owner, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(StoreContext::class)->resolve($owner);

        return $client;
    }

    public function test_fashion_settings_and_site_settings_share_the_site_group_without_wiping_each_other(): void
    {
        $this->signInOwnerOf('fashion', 'atelier');

        Livewire::test(FashionSettings::class)
            ->assertSuccessful()
            ->set('data.site.logo_primary', 'Atelier')
            ->set('data.site.announcement_text', 'Free alterations this week')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(SiteSettings::class)
            ->assertSuccessful()
            ->assertDontSee('Wordmark — first word')
            ->set('data.site.contact_phone', '+91 90000 11111')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Atelier', Setting::get('site.logo_primary'));
        $this->assertSame('Free alterations this week', Setting::get('site.announcement_text'));
        $this->assertSame('+91 90000 11111', Setting::get('site.contact_phone'));

        // And the other way round: saving the Fashion page keeps the contact phone.
        Livewire::test(FashionSettings::class)->set('data.site.footer_blurb', 'Made in Jaipur')->call('save')->assertHasNoErrors();
        $this->assertSame('+91 90000 11111', Setting::get('site.contact_phone'));
        $this->assertSame('Made in Jaipur', Setting::get('site.footer_blurb'));
    }

    public function test_the_tax_section_only_sets_whether_prices_include_tax(): void
    {
        $this->signInOwnerOf('fashion', 'atelier');
        // Rates come from each product's tax now; a rate saved earlier is left as it was.
        Setting::set('checkout', ['tax_mode' => 'exclusive', 'tax_rate' => 18] + config('client_defaults.checkout'));

        $page = Livewire::test(SiteSettings::class);
        $this->assertStringNotContainsString('data.checkout.tax_rate', $page->html());
        $this->assertStringNotContainsString('data.checkout.tax_label', $page->html());

        foreach ([null, '', 'both', 'INCLUSIVE'] as $mode) {
            Livewire::test(SiteSettings::class)->set('data.checkout.tax_mode', $mode)->call('save')
                ->assertHasErrors(['data.checkout.tax_mode'], 'mode '.var_export($mode, true));
        }

        foreach (['inclusive', 'exclusive'] as $mode) {
            Livewire::test(SiteSettings::class)->set('data.checkout.tax_mode', $mode)->call('save')->assertHasNoErrors();
            $this->assertSame($mode, Setting::get('checkout.tax_mode'));
            $this->assertEquals(18, Setting::get('checkout.tax_rate'));
        }
    }

    public function test_clear_empties_only_the_open_tab_and_nothing_is_stored_until_save(): void
    {
        $this->signInOwnerOf('fashion', 'atelier');
        Setting::set('site', [
            'announcement_text' => 'Old sale', 'announcement_link_url' => 'https://example.test',
            'logo_primary' => 'Atelier', 'contact_phone' => '+91 90000 11111',
        ]);

        $page = Livewire::test(FashionSettings::class)
            ->callAction(TestAction::make('clear')->schemaComponent('form-actions', schema: 'content')->arguments(['tab' => 'form.announcement-bar::data::tab']))
            ->assertSet('data.site.announcement_text', null)
            ->assertSet('data.site.announcement_link_url', null)
            ->assertSet('data.site.logo_primary', 'Atelier');

        $this->assertSame('Old sale', Setting::get('site.announcement_text'), 'nothing is stored before Save');

        $page->call('save')->assertHasNoErrors();
        $this->assertEmpty(Setting::get('site.announcement_text'));
        $this->assertEmpty(Setting::get('site.announcement_link_url'));
        $this->assertSame('Atelier', Setting::get('site.logo_primary'));
        $this->assertSame('+91 90000 11111', Setting::get('site.contact_phone'));
    }

    public function test_clear_with_an_unknown_tab_changes_nothing(): void
    {
        $this->signInOwnerOf('fashion', 'atelier');
        Setting::set('site', ['announcement_text' => 'Old sale', 'logo_primary' => 'Atelier']);

        Livewire::test(FashionSettings::class)
            ->callAction(TestAction::make('clear')->schemaComponent('form-actions', schema: 'content')->arguments(['tab' => 'form.nope::data::tab']))
            ->assertSet('data.site.announcement_text', 'Old sale')
            ->assertSet('data.site.logo_primary', 'Atelier');
    }

    public function test_each_store_owner_sees_only_their_templates_settings_page(): void
    {
        $this->signInOwnerOf('fashion', 'atelier');
        $this->assertTrue(FashionSettings::canAccess());
        $this->assertFalse(HeritageSettings::canAccess());
        $this->assertFalse(BulkOrderSettings::canAccess());
    }

    public function test_the_bulk_order_settings_page_is_on_the_store_panel(): void
    {
        $this->signInOwnerOf('bulk', 'trade');

        $this->assertContains(BulkOrderSettings::class, Filament::getPanel('client')->getPages());
        $this->assertTrue(BulkOrderSettings::canAccess());
        $this->assertFalse(FashionSettings::canAccess());
    }

    public function test_each_site_settings_tab_links_to_where_it_shows_on_the_store(): void
    {
        $client = $this->signInOwnerOf('fashion', 'atelier');
        $store = rtrim($client->publicUrl(), '/');

        // One button at the right of the tab bar, shown for the open tab.
        $page = Livewire::test(SiteSettings::class)->assertSuccessful();
        $html = $page->html();
        foreach (['brand' => '/?settings_preview=brand', 'contact-social' => '/?settings_preview=contact', 'checkout' => '/checkout?settings_preview=checkout'] as $tab => $path) {
            $this->assertStringContainsString(e($store.$path), $html);
            $this->assertStringContainsString('data-tab-key="'.$tab.'::data::tab"', $html, "the button follows the {$tab} tab");
            $this->assertMatchesRegularExpression('/x-show="tab === [^"]*'.preg_quote($tab, '/').'::data::tab/', $html);
        }
        // Reviews need a product to show on; with none, the button is left out.
        $page->assertDontSee('settings_preview=reviews', false);
    }

    public function test_the_store_explains_a_previewed_settings_tab_and_ignores_unknown_ones(): void
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $storefront = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $storefront->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $store = $this->withSession([Client::SESSION_KEY => 'alpha']);

        $store->get('/shop?settings_preview=contact')->assertOk()
            ->assertSee('data-settings-preview-banner', false)
            ->assertSee('payment badges are outlined in orange', false)
            ->assertSee('<meta name="robots" content="noindex">', false);

        $store->get('/shop?settings_preview=%3Cscript%3E')->assertOk()->assertDontSee('data-settings-preview-banner', false);
    }

    public function test_a_bulk_store_saves_its_contact_and_social_details(): void
    {
        $this->signInOwnerOf('bulk', 'wholesale');

        Livewire::test(SiteSettings::class)
            ->set('data.site.social_instagram', 'https://instagram.com/wholesale')
            ->set('data.site.contact_email', 'care@wholesale.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('https://instagram.com/wholesale', Setting::get('site.social_instagram'));
        $this->assertSame('care@wholesale.test', Setting::get('site.contact_email'));

        // The bulk footer shows them: the social link and the payment badges.
        $footer = view('templates.bulk.partials.footer', ['menus' => []])->render();
        $this->assertStringContainsString('href="https://instagram.com/wholesale"', $footer);
        $this->assertStringContainsString('aria-label="Accepted payment methods"', $footer);
    }

    public function test_the_checkout_preview_shows_a_sample_bag_without_touching_the_real_one(): void
    {
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $storefront = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $storefront->id, 'plan_id' => $plan->id, 'is_active' => true]);
        $store = $this->withSession([Client::SESSION_KEY => 'alpha']);

        // No product in stock: nothing to preview with, so the empty bag as usual.
        $store->get('/checkout?settings_preview=checkout')->assertRedirect(route('cart'));

        $own = ['client_id' => $client->id, 'template' => 'fashion'];
        $category = Category::create($own + ['name' => 'Sarees', 'slug' => 'sarees', 'is_active' => true]);
        Product::create($own + ['name' => 'Chanderi Saree', 'slug' => 'chanderi-saree', 'category_id' => $category->id, 'price' => 8900, 'stock' => 20, 'is_active' => true, 'images' => []]);

        $store->get('/checkout?settings_preview=checkout')->assertOk()
            ->assertSee('Chanderi Saree')
            ->assertSee('name="shipping_method"', false)
            ->assertSee('Preview with a sample item', false)
            ->assertSessionMissing('cart.items');

        // Without the preview the bag is still empty.
        $store->get('/checkout')->assertRedirect(route('cart'));
    }

    public function test_a_signed_in_team_member_can_preview_the_sign_in_page(): void
    {
        $client = $this->signInOwnerOf('fashion', 'atelier');

        // The Sign-in page tab links to it.
        Livewire::test(SiteSettings::class)
            ->assertSee(e(rtrim($client->publicUrl(), '/').'/client/login?settings_preview=login'), false)
            ->assertSee('View sign-in page');

        // Signed in, the page shows instead of the dashboard, says it is a preview, and its form does nothing.
        Livewire::withQueryParams(['settings_preview' => 'login'])->test(Login::class)
            ->assertSee('Preview of your team')
            ->assertNoRedirect()
            ->set('data.email', 'owner@atelier.test')
            ->set('data.password', 'secret-pass')
            ->call('authenticate')
            ->assertNoRedirect()
            ->assertNotified('This is a preview');

        // Without the preview link it is the normal page (Filament sends a signed-in member on).
        Livewire::withQueryParams([])->test(Login::class)->assertSet('settingsPreview', false)->assertDontSee('Preview of your team');
    }

    public function test_a_service_business_has_no_checkout_or_reviews_tab(): void
    {
        $this->signInOwnerOf('plumbing-services', 'pipecare');

        $page = Livewire::test(SiteSettings::class)->assertSuccessful();
        $html = $page->html();
        $this->assertStringNotContainsString('data-tab-key="checkout::data::tab"', $html);
        $this->assertStringNotContainsString('data-tab-key="reviews::data::tab"', $html);
        $this->assertStringNotContainsString('settings_preview=checkout', $html);
        $this->assertStringContainsString('data-tab-key="contact-social::data::tab"', $html);

        // Saving still works without the hidden tab's required fields.
        $page->set('data.site.contact_phone', '+91 90000 22222')->call('save')->assertHasNoErrors();
        $this->assertSame('+91 90000 22222', Setting::get('site.contact_phone'));
    }
}
