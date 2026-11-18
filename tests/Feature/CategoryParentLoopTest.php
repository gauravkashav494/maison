<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\Storefront;
use App\Rules\NotOwnDescendantCategory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A category can't be placed under itself or any of its own sub-categories, so the tree
 * never holds a loop (A → B → A, or A → B → C → A).
 */
class CategoryParentLoopTest extends TestCase
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

    private function passes(Category $category, ?int $parentId): bool
    {
        return Validator::make(['parent_id' => $parentId], ['parent_id' => [new NotOwnDescendantCategory($category)]])->passes();
    }

    public function test_descendants_are_found_at_every_depth(): void
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        $c = $this->category('C', $b);
        $other = $this->category('Other');

        $this->assertEqualsCanonicalizing([$b->id, $c->id], $a->descendantIds());
        $this->assertSame([$c->id], $b->descendantIds());
        $this->assertSame([], $c->descendantIds());
        $this->assertSame([], $other->descendantIds());
    }

    public function test_descendant_lookup_ends_when_the_data_already_holds_a_loop(): void
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        // A query update skips the model guard, the way old or imported data might.
        Category::withoutGlobalScopes()->whereKey($a->id)->update(['parent_id' => $b->id]);

        $this->assertSame([$b->id], $a->fresh()->descendantIds());
    }

    public function test_the_model_refuses_to_save_a_loop_from_any_code(): void
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        $c = $this->category('C', $b);

        foreach ([$a, $b, $c] as $parent) {
            try {
                $a->update(['parent_id' => $parent->id]);
                $this->fail("Saving A under {$parent->name} should have been refused.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('parent_id', $e->errors());
            }
        }

        $this->assertNull($a->fresh()->parent_id);
    }

    public function test_the_model_still_saves_valid_moves(): void
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        $c = $this->category('C', $b);
        $other = $this->category('Other');

        $c->update(['parent_id' => $a->id]);   // move up the tree
        $a->update(['parent_id' => $other->id]); // under an unrelated category
        $a->update(['name' => 'A renamed']);      // unrelated edit
        $b->update(['parent_id' => null]);        // back to top level

        $this->assertSame($a->id, $c->fresh()->parent_id);
        $this->assertSame($other->id, $a->fresh()->parent_id);
        $this->assertNull($b->fresh()->parent_id);
    }

    public function test_the_rule_rejects_itself_a_child_and_a_grandchild(): void
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        $c = $this->category('C', $b);
        $other = $this->category('Other');

        $this->assertFalse($this->passes($a, $a->id));
        $this->assertFalse($this->passes($a, $b->id)); // A ↔ B
        $this->assertFalse($this->passes($a, $c->id)); // A → B → C → A
        $this->assertTrue($this->passes($a, $other->id));
        $this->assertTrue($this->passes($a, null));
        $this->assertTrue($this->passes($c, $a->id)); // moving up the tree is fine
    }

    public function test_the_form_does_not_save_a_sub_category_as_parent(): void
    {
        $a = $this->category('A');
        $b = $this->category('B', $a);
        $c = $this->category('C', $b);
        $this->signIn();

        foreach ([$b, $c] as $descendant) {
            Livewire::test(EditCategory::class, ['record' => $a->getRouteKey()])
                ->fillForm(['parent_id' => $descendant->id])
                ->call('save')
                ->assertHasFormErrors(['parent_id']);
        }

        $this->assertNull($a->fresh()->parent_id);
    }

    public function test_the_form_still_saves_a_valid_parent(): void
    {
        $a = $this->category('A');
        $other = $this->category('Other');
        $this->signIn();

        Livewire::test(EditCategory::class, ['record' => $a->getRouteKey()])
            ->fillForm(['parent_id' => $other->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($other->id, $a->fresh()->parent_id);
    }
}
