<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Schemas\ClientForm;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A deactivated store cannot be picked for a new client, but an existing client keeps its own. */
class ClientStorefrontChoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_stores_are_offered_for_a_new_client(): void
    {
        $active = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $inactive = Storefront::create(['name' => 'Grocery Store', 'slug' => 'grocery', 'template' => 'grocery', 'is_active' => false]);

        $options = ClientForm::selectableStorefronts(null);

        $this->assertTrue($options->has($active->id));
        $this->assertFalse($options->has($inactive->id));
    }

    public function test_an_existing_client_still_sees_its_deactivated_store(): void
    {
        $active = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $inactive = Storefront::create(['name' => 'Grocery Store', 'slug' => 'grocery', 'template' => 'grocery', 'is_active' => false]);
        $other = Storefront::create(['name' => 'Heritage Store', 'slug' => 'heritage', 'template' => 'heritage', 'is_active' => false]);
        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $inactive->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $options = ClientForm::selectableStorefronts($client);

        $this->assertSame([$active->id, $inactive->id], $options->keys()->sort()->values()->all());
        $this->assertFalse($options->has($other->id));
    }
}
