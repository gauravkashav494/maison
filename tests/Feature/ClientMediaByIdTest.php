<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** A client's uploads live in clients/<id>/, and older clients/<subdomain>/ folders are moved there. */
class ClientMediaByIdTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    public function test_the_media_folder_is_named_by_client_id(): void
    {
        $this->assertSame('clients/'.$this->client->id, $this->client->mediaDirectory());
    }

    public function test_the_command_moves_the_files_and_rewrites_every_stored_path(): void
    {
        $disk = Storage::disk('public');
        $disk->put('clients/alpha/products/a.png', 'a');
        $disk->put('clients/alpha/uploads/logo.png', 'b');
        $disk->put('clients/alpha-two/products/other.png', 'c'); // another client whose subdomain starts the same

        $product = Product::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Coat', 'slug' => 'coat', 'price' => 1000, 'is_active' => true,
            'images' => ['clients/alpha/products/a.png', 'https://example.test/sample.jpg', 'clients/alpha-two/products/other.png'],
        ]);
        Setting::create(['key' => 'client:'.$this->client->id.':site', 'value' => ['logo' => 'clients/alpha/uploads/logo.png']]);

        $this->artisan('clients:media-by-id', ['--dry-run' => true])->assertSuccessful();
        $this->assertTrue($disk->exists('clients/alpha/products/a.png'), 'A dry run changes nothing.');

        $this->artisan('clients:media-by-id')->assertSuccessful();

        $id = $this->client->id;
        $this->assertTrue($disk->exists("clients/{$id}/products/a.png"));
        $this->assertTrue($disk->exists("clients/{$id}/uploads/logo.png"));
        $this->assertFalse($disk->exists('clients/alpha'));
        $this->assertTrue($disk->exists('clients/alpha-two/products/other.png'), 'Another client is not touched.');

        $this->assertSame(
            ["clients/{$id}/products/a.png", 'https://example.test/sample.jpg', 'clients/alpha-two/products/other.png'],
            $product->fresh()->images,
        );
        $this->assertSame("clients/{$id}/uploads/logo.png", Setting::where('key', 'client:'.$id.':site')->value('value')['logo']);

        // Running it again finds nothing left to do.
        $this->artisan('clients:media-by-id')->assertSuccessful();
        $this->assertSame("clients/{$id}/products/a.png", $product->fresh()->images[0]);
    }
}
