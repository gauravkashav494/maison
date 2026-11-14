<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Pages\BulkOrderSettings;
use App\Filament\Pages\FashionSettings;
use App\Filament\Pages\HeritageSettings;
use App\Filament\Pages\SiteSettings;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Storefront;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Site settings hold what every store shares (name, contact, checkout, SEO…); each template's
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
}
