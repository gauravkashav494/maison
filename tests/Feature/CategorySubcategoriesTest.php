<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Plan;
use App\Models\PlatformActivity;
use App\Models\Product;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Sub-categories are typed on the category form (Shirts, Pants, Suits under Men) and made on save. */
class CategorySubcategoriesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Priya Sharma', 'email' => 'priya@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    public function test_creating_a_category_also_creates_the_sub_categories_added_as_rows(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Men', 'slug' => 'men', 'children' => ['a' => ['name' => 'Shirts'], 'b' => ['name' => 'Pants'], 'c' => ['name' => 'Suits']]])
            ->call('create')
            ->assertHasNoFormErrors();

        $men = Category::where('slug', 'men')->firstOrFail();
        $children = $men->children()->get();
        $this->assertSame(['Shirts', 'Pants', 'Suits'], $children->pluck('name')->all());
        $this->assertSame(['men-shirts', 'men-pants', 'men-suits'], $children->pluck('slug')->all());
        $this->assertSame([1, 2, 3], $children->pluck('sort_order')->map(fn ($n) => (int) $n)->all());
        $this->assertTrue($children->every(fn (Category $c) => $c->client_id === $this->client->id && $c->is_active && $c->show_in_menu));

        // Each one is a line of its own in the activity log, for this client.
        $lines = PlatformActivity::where('subject_type', 'Category')->where('action', 'created')->get();
        $this->assertCount(4, $lines);
        $this->assertTrue($lines->every(fn (PlatformActivity $l) => $l->client_id === $this->client->id && $l->actor === 'Priya Sharma'));
    }

    public function test_the_same_sub_category_twice_is_refused_whatever_the_capitals(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Men', 'slug' => 'men', 'children' => ['a' => ['name' => 'Shirts'], 'b' => ['name' => 'shirts']]])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, Category::count());
    }

    public function test_editing_shows_the_sub_categories_as_rows_to_rename_remove_or_add(): void
    {
        $men = Category::create(['name' => 'Men', 'slug' => 'men', 'is_active' => true, 'show_in_menu' => true]);
        [$shirts, $pants, $suits] = $men->addChildren(['Shirts', 'Pants', 'Suits']);
        Category::create(['name' => 'Old blazers page', 'slug' => 'men-blazers', 'is_active' => true]);

        $form = Livewire::test(EditCategory::class, ['record' => $men->getRouteKey()])
            ->assertSet('data.children', fn ($rows) => array_column(array_values((array) $rows), 'name') === ['Shirts', 'Pants', 'Suits']);

        // Rename Pants, remove Suits, add Blazers.
        $rows = $form->get('data.children');
        $rows['record-'.$pants->id]['name'] = 'Trousers';
        unset($rows['record-'.$suits->id]);
        $rows['new'] = ['name' => 'Blazers'];
        $form->set('data.children', $rows)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Shirts', 'Trousers', 'Blazers'], $men->children()->pluck('name')->all());
        $this->assertSame('men-pants', $pants->fresh()->slug);              // renaming keeps the address
        $this->assertSame('men-blazers-2', $men->children()->where('name', 'Blazers')->value('slug'));
        $this->assertTrue($suits->fresh()->trashed());                        // removed: in the trash
    }

    public function test_categories_are_in_the_dragged_order_then_a_to_z(): void
    {
        // No sort order box: the order is set by dragging rows in the categories list.
        Livewire::test(CreateCategory::class)->assertFormFieldDoesNotExist('sort_order');

        $women = Category::create(['name' => 'Women', 'slug' => 'women', 'is_active' => true]);
        $kids = Category::create(['name' => 'Kids', 'slug' => 'kids', 'is_active' => true, 'sort_order' => 1]);
        $men = Category::create(['name' => 'Men', 'slug' => 'men', 'is_active' => true]);
        $tops = Category::create(['name' => 'Tops', 'slug' => 'women-tops', 'parent_id' => $women->id, 'is_active' => true]);
        $bags = Category::create(['name' => 'Bags', 'slug' => 'women-bags', 'parent_id' => $women->id, 'is_active' => true]);

        // Men and Women both sit at 0 (never dragged): A to Z, then Kids at 1. Same below Women.
        $this->assertSame([$men->id, $women->id, $kids->id], Category::topLevel()->pluck('id')->all());
        $this->assertSame([$bags->id, $tops->id], $women->children()->pluck('id')->all());
    }

    public function test_the_list_shows_the_sub_categories_of_each_category(): void
    {
        $men = Category::create(['name' => 'Men', 'slug' => 'men', 'is_active' => true]);
        $men->addChildren(['Shirts', 'Pants', 'Suits']);

        Livewire::test(ListCategories::class)
            ->assertTableColumnExists('children.name')
            ->assertTableColumnStateSet('children.name', ['Shirts', 'Pants', 'Suits'], $men);
    }

    public function test_categories_sharing_a_name_are_shown_with_their_parents(): void
    {
        $men = Category::create(['name' => 'Men', 'slug' => 'men', 'is_active' => true]);
        $women = Category::create(['name' => 'Women', 'slug' => 'women', 'is_active' => true]);
        [$menShirts] = $men->addChildren(['Shirts']);
        [$womenShirts] = $women->addChildren(['Shirts']);
        [$formal] = $menShirts->addChildren(['Formal']);

        $this->assertSame('Men › Shirts', $menShirts->pathName());
        $this->assertSame('Women › Shirts', $womenShirts->pathName());
        $this->assertSame('Men › Shirts › Formal', $formal->pathName());

        Livewire::test(ListCategories::class)
            // The name on its own; the parent column says where it sits.
            ->assertTableColumnFormattedStateSet('name', 'Shirts', $formal->parent)
            ->assertTableColumnFormattedStateSet('parent.name', 'Men', $menShirts)
            ->assertTableColumnFormattedStateSet('parent.name', 'Women', $womenShirts)
            ->assertTableColumnFormattedStateSet('parent.name', 'Men › Shirts', $formal);
    }

    public function test_the_slug_is_made_from_the_name_and_kept_when_renamed(): void
    {
        Livewire::test(CreateCategory::class)
            ->assertFormFieldDoesNotExist('slug')
            ->fillForm(['name' => 'Men', 'children' => ['a' => ['name' => 'Winter Wear']]])
            ->call('create')
            ->assertHasNoFormErrors();
        $men = Category::where('name', 'Men')->firstOrFail();
        $this->assertSame('men', $men->slug);
        $this->assertSame('men-winter-wear', $men->children()->value('slug'));

        // A product: from its name, with -2 when the name is taken, and kept on rename.
        foreach (['Oxford Shirt', 'Oxford Shirt'] as $name) {
            Livewire::test(CreateProduct::class)
                ->assertFormFieldDoesNotExist('slug')
                ->fillForm(['category_level_0' => $men->id, 'name' => $name, 'price_excl_tax' => 999])
                ->call('create')
                ->assertHasNoFormErrors();
        }
        $this->assertSame(['oxford-shirt', 'oxford-shirt-2'], Product::orderBy('id')->pluck('slug')->all());

        $product = Product::where('slug', 'oxford-shirt')->firstOrFail();
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Oxford Shirt, White'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('oxford-shirt', $product->fresh()->slug);

        // A slug in the trash still counts as taken: restoring it must not clash. Its sub-category
        // goes first, as a category with sub-categories can't be deleted.
        $men->children()->get()->each->delete();
        $men->delete();
        $this->assertSame('men-2', Category::create(['name' => 'Men', 'is_active' => true])->slug);
    }
}
