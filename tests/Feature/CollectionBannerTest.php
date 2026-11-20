<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Collection;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Collections have no "Featured" switch: the Fashion homepage banner is the collection picked in
 * Homepage settings → Featured collection, and nothing else. The is_featured column is kept but
 * unused.
 */
class CollectionBannerTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function collection(string $name, array $extra = []): Collection
    {
        return Collection::withoutGlobalScopes()->create($extra + ['client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true, 'description' => $name.' story']);
    }

    public function test_the_homepage_banner_is_only_the_collection_picked_in_homepage_settings(): void
    {
        $this->assertTrue(Schema::hasColumn('collections', 'is_featured'), 'The column is kept.');
        $summer = $this->collection('Summer Linen');
        // A collection still flagged in the old column is not the banner.
        $flagged = $this->collection('Old Flag');
        DB::table('collections')->where('id', $flagged->id)->update(['is_featured' => true]);
        $url = $this->client->publicUrl();

        $this->get($url.'/')->assertSuccessful()->assertDontSee('Explore the Collection');

        Setting::query()->create(['key' => 'client:'.$this->client->id.':home', 'value' => ['featured_collection_id' => $summer->id]]);
        Cache::forget('settings.all');

        $this->get($url.'/')->assertSuccessful()->assertSee('Explore the Collection')->assertSee('Summer Linen');
    }
}
