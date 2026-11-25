<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Exceptions\CategoryInUseException;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A category with sub-categories can't go to the trash, and a sub-category can't come back while
 * its parent is in the trash. Otherwise the sub-category points at a deleted parent: "Top level"
 * in the panel, yet in no storefront menu.
 */
class CategoryDeleteTest extends TestCase
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

    private function category(string $name, ?Category $parent = null): Category
    {
        return Category::withoutGlobalScopes()->create([
            'client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name,
            'slug' => str($name)->slug()->value(), 'parent_id' => $parent?->id, 'is_active' => true,
        ]);
    }

    private function signIn(): void
    {
        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    public function test_the_model_refuses_to_trash_a_category_with_sub_categories(): void
    {
        $men = $this->category('Men');
        $this->category('Shirts', $men);

        $this->expectException(CategoryInUseException::class);
        $this->expectExceptionMessage('"Men" still has 1 sub-category: Shirts.');

        $men->delete();
    }

    public function test_a_category_whose_sub_categories_are_all_in_the_trash_can_be_deleted(): void
    {
        $men = $this->category('Men');
        $this->category('Shirts', $men)->delete();

        $men->delete();

        $this->assertSoftDeleted($men);
    }

    public function test_the_model_refuses_to_restore_a_sub_category_while_its_parent_is_in_the_trash(): void
    {
        $men = $this->category('Men');
        $shirts = $this->category('Shirts', $men);
        $shirts->delete();
        $men->delete();

        try {
            $shirts->restore();
            $this->fail('Restoring under a deleted parent should be refused.');
        } catch (CategoryInUseException $e) {
            $this->assertSame('Its parent category "Men" is in the trash. Restore "Men" first.', $e->getMessage());
        }
        $this->assertSoftDeleted($shirts);

        $men->restore();
        $shirts->restore();
        $this->assertNotSoftDeleted($shirts);
    }

    public function test_delete_in_the_list_is_refused_with_a_message_and_nothing_changes(): void
    {
        $men = $this->category('Men');
        $this->category('Shirts', $men);
        $this->category('Pants', $men);
        $this->signIn();

        Livewire::test(ListCategories::class)
            ->callAction(TestAction::make('delete')->table($men))
            ->assertNotified("Can't delete this category");

        $this->assertNotSoftDeleted($men);
    }

    public function test_delete_on_the_edit_page_is_refused_with_a_message(): void
    {
        $men = $this->category('Men');
        $this->category('Shirts', $men);
        $this->signIn();

        Livewire::test(EditCategory::class, ['record' => $men->getRouteKey()])
            ->callAction('delete')
            ->assertNotified("Can't delete this category");

        $this->assertNotSoftDeleted($men);
    }

    public function test_bulk_delete_is_refused_as_a_whole_when_any_selected_category_has_sub_categories(): void
    {
        $men = $this->category('Men');
        $shirts = $this->category('Shirts', $men);
        $sale = $this->category('Sale');
        $this->signIn();

        Livewire::test(ListCategories::class)
            ->selectTableRecords([$men->id, $shirts->id, $sale->id])
            ->callAction(TestAction::make('delete')->table()->bulk())
            ->assertNotified("Can't delete these categories");

        $this->assertNotSoftDeleted($men);
        $this->assertNotSoftDeleted($shirts);
        $this->assertNotSoftDeleted($sale);
    }

    public function test_a_category_without_sub_categories_still_deletes_from_the_list(): void
    {
        $men = $this->category('Men');
        $shirts = $this->category('Shirts', $men);
        $this->signIn();

        Livewire::test(ListCategories::class)
            ->callAction(TestAction::make('delete')->table($shirts));

        $this->assertSoftDeleted($shirts);
        $this->assertNotSoftDeleted($men);
    }

    public function test_restore_in_the_list_is_refused_while_the_parent_is_in_the_trash(): void
    {
        $men = $this->category('Men');
        $shirts = $this->category('Shirts', $men);
        $shirts->delete();
        $men->delete();
        $this->signIn();

        Livewire::test(ListCategories::class)
            ->filterTable('trashed', true)
            ->callAction(TestAction::make('restore')->table($shirts))
            ->assertNotified("Can't restore this category");

        $this->assertSoftDeleted($shirts);
    }
}
