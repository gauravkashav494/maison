<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientRole;
use App\Models\Plan;
use App\Models\Storefront;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * clients:sync-roles: the ready-made roles of existing clients gain modules added since, without
 * giving back what a Client Admin took away or touching roles they made.
 */
class ClientsSyncRolesTest extends TestCase
{
    use RefreshDatabase;

    private Client $alpha;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion Store', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->alpha = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function role(string $name): ClientRole
    {
        return ClientRole::withTrashed()->where('client_id', $this->alpha->id)->where('name', $name)->firstOrFail();
    }

    public function test_a_ready_made_role_gains_only_modules_it_does_not_mention(): void
    {
        // As a Catalogue role made before Brands and Taxes existed, with "delete products" taken away by hand.
        $catalogue = $this->role('Catalogue');
        $catalogue->update(['permissions' => ['products.view', 'products.create', 'products.update', 'inventory.view', 'inventory.update']]);

        $this->artisan('clients:sync-roles')->assertSuccessful();

        $permissions = $this->role('Catalogue')->permissions;
        foreach (['brands.view', 'brands.create', 'brands.update', 'brands.delete', 'taxes.view', 'categories.view'] as $added) {
            $this->assertContains($added, $permissions);
        }
        $this->assertNotContains('products.delete', $permissions);
    }

    public function test_own_roles_and_deleted_ready_made_roles_are_left_alone_and_missing_ones_created(): void
    {
        ClientRole::create(['client_id' => $this->alpha->id, 'name' => 'Packers', 'permissions' => ['orders.view']]);
        $this->role('Customer support')->delete();
        $this->role('Content editor')->forceDelete();

        $this->artisan('clients:sync-roles', ['subdomain' => 'alpha'])->assertSuccessful();

        $this->assertSame(['orders.view'], $this->role('Packers')->permissions);
        $this->assertTrue($this->role('Customer support')->trashed());
        $this->assertFalse($this->role('Content editor')->trashed());
    }

    public function test_a_dry_run_saves_nothing(): void
    {
        $this->role('Catalogue')->update(['permissions' => ['products.view']]);

        $this->artisan('clients:sync-roles', ['--dry-run' => true])
            ->expectsOutputToContain('would change (dry run, nothing saved)')
            ->assertSuccessful();

        $this->assertSame(['products.view'], $this->role('Catalogue')->permissions);
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->artisan('clients:sync-roles')->assertSuccessful();
        $this->artisan('clients:sync-roles')->expectsOutput('Every ready-made role is up to date.')->assertSuccessful();
    }
}
