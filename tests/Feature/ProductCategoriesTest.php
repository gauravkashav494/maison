<?php

namespace Tests\Feature;

use App\Admin\StoreContext;
use App\Clients\ClientContext;
use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Collection;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Storefront;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A product is listed under several categories (one of them its main category: card label and
 * breadcrumb) and sits in at most one collection.
 */
class ProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    private Category $shirts;

    private Category $linen;

    private Category $sale;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Fashion', 'slug' => 'fashion', 'template' => 'fashion', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);

        $category = fn (string $name) => Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true, 'show_in_menu' => true]);
        $this->shirts = $category('Shirts');
        $this->linen = $category('Linen');
        $this->sale = $category('Gifts');
    }

    private function signIn(): void
    {
        $admin = ClientUser::create(['client_id' => $this->client->id, 'name' => 'Admin', 'email' => 'admin@alpha.test', 'password' => 'password123', 'role' => ClientUser::ROLE_CLIENT_ADMIN, 'is_active' => true]);
        $this->actingAs($admin, 'client');
        Filament::setCurrentPanel(Filament::getPanel('client'));
        app(ClientContext::class)->resolveForIdentity($admin);
        app(StoreContext::class)->resolve($admin, app(ClientContext::class)->templateId());
    }

    private function collection(string $name): Collection
    {
        return Collection::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => $name, 'slug' => str($name)->slug()->value(), 'is_active' => true]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->client->id, 'template' => 'fashion', 'category_id' => $this->shirts->id,
            'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'price' => 2000, 'stock' => 5, 'is_active' => true,
        ]);
    }

    public function test_the_form_saves_the_picked_category_as_main_and_lists_it_under_the_others_too(): void
    {
        $this->signIn();
        $summer = $this->collection('Summer');

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'category_level_0' => $this->linen->id, 'extra_category_1' => $this->sale->id, 'collection' => $summer->id, 'price_excl_tax' => 2000])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::withoutGlobalScopes()->where('slug', 'linen-shirt')->firstOrFail();
        $this->assertSame($this->linen->id, $product->category_id);
        $this->assertEqualsCanonicalizing([$this->linen->id, $this->sale->id], $product->categories()->pluck('categories.id')->all());
        $this->assertSame([$summer->id], $product->collections()->pluck('collections.id')->all());
    }

    public function test_at_least_one_category_is_required(): void
    {
        $this->signIn();

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'category_level_0' => null, 'price_excl_tax' => 2000])
            ->call('create')
            ->assertHasFormErrors(['category_level_0' => 'required']);
    }

    public function test_editing_keeps_the_main_category_and_changes_the_extra_places(): void
    {
        $product = $this->product();
        $product->categories()->attach($this->sale->id);
        $this->signIn();

        // Sections 1–2 hold the main category; 3 and 4 the extra places.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSet('data.category_level_0', $this->shirts->id)
            ->assertSet('data.extra_category_1', $this->sale->id)
            ->assertSet('data.extra_category_2', null)
            ->fillForm(['extra_category_2' => $this->linen->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($this->shirts->id, $product->fresh()->category_id);
        $this->assertCount(3, $product->categories()->get());

        // Picking another main category drops the old one, unless it is kept as an extra place.
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['category_level_0' => $this->linen->id, 'extra_category_1' => $this->sale->id, 'extra_category_2' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($this->linen->id, $product->fresh()->category_id);
        $this->assertEqualsCanonicalizing([$this->linen->id, $this->sale->id], $product->categories()->pluck('categories.id')->all());
    }

    public function test_four_sections_category_sub_category_and_two_extra_places(): void
    {
        $category = fn (string $name, ?Category $parent = null) => Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'parent_id' => $parent?->id, 'name' => $name, 'slug' => str(($parent?->slug ?? '').' '.$name)->slug()->value(), 'is_active' => true]);
        $men = $category('Men');
        $menShirts = $category('Shirts', $men);
        $pants = $category('Pants', $men);
        $women = $category('Women');
        $womenShirts = $category('Shirts', $women);
        $winter = $category('Winter Wear');
        $this->signIn();

        $form = Livewire::test(CreateProduct::class)
            // All four sections from the start; the sub-category waits for a category.
            ->assertFormFieldVisible('category_level_0')
            ->assertFormFieldVisible('category_level_1')
            ->assertFormFieldDisabled('category_level_1')
            ->assertFormFieldVisible('extra_category_1')
            ->assertFormFieldVisible('extra_category_2')
            ->assertFormFieldHidden('category_level_2')
            ->fillForm(['category_level_0' => $men->id])
            ->assertFormFieldEnabled('category_level_1')
            // Sub-category: only Men's.
            ->assertFormFieldExists('category_level_1', fn ($field) => collect(array_keys($field->getOptions()))->sort()->values()->all() === collect([$menShirts->id, $pants->id])->sort()->values()->all())
            ->fillForm(['category_level_1' => $menShirts->id])
            // No Type box: Shirts has nothing under it.
            ->assertFormFieldHidden('category_level_2')
            // The extra places offer everything except what is picked before them, each by its own
            // name with the categories above it on the right, so the two Shirts are told apart.
            ->assertFormFieldExists('extra_category_1', fn ($field) => ! array_key_exists($men->id, $field->getOptions())
                && ! array_key_exists($menShirts->id, $field->getOptions())
                && str_starts_with($field->getOptions()[$womenShirts->id] ?? '', '<span')
                && str_contains($field->getOptions()[$womenShirts->id], 'Shirts<span class="fi-cat-option-path">')
                && str_contains($field->getOptions()[$womenShirts->id], '>Women</span>')
                && ($field->getOptions()[$winter->id] ?? null) === 'Winter Wear')
            ->fillForm(['extra_category_1' => $winter->id])
            ->assertFormFieldExists('extra_category_2', fn ($field) => ! array_key_exists($winter->id, $field->getOptions()));

        // Changing the category clears the sub-category.
        $form->fillForm(['category_level_0' => $women->id])->assertSet('data.category_level_1', null);
        // A category with nothing under it keeps the box, switched off.
        $form->fillForm(['category_level_0' => $winter->id])->assertFormFieldVisible('category_level_1')->assertFormFieldDisabled('category_level_1');

        $form->fillForm([
            'category_level_0' => $men->id, 'category_level_1' => $menShirts->id,
            'extra_category_1' => $winter->id, 'extra_category_2' => $womenShirts->id,
            'name' => 'Flannel Shirt', 'price_excl_tax' => 1800,
        ])->call('create')->assertHasNoFormErrors();

        $product = Product::withoutGlobalScopes()->where('name', 'Flannel Shirt')->firstOrFail();
        $this->assertSame($menShirts->id, $product->category_id);
        $this->assertEqualsCanonicalizing([$menShirts->id, $winter->id, $womenShirts->id], $product->categories()->pluck('categories.id')->all());

        // Editing opens with all four filled in.
        $extras = [$winter->id, $womenShirts->id];
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSet('data.category_level_0', $men->id)
            ->assertSet('data.category_level_1', $menShirts->id)
            ->assertSet('data.extra_category_1', fn ($id) => in_array((int) $id, $extras, true))
            ->assertSet('data.extra_category_2', fn ($id) => in_array((int) $id, $extras, true));

        // Listed in more extra places than there are boxes: the ones that do not fit are kept.
        $product->categories()->attach([$pants->id, $this->sale->id]);
        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Flannel Shirt, red'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertCount(5, $product->categories()->get());
    }

    public function test_the_category_box_lists_sub_categories_too_and_one_can_be_picked_directly(): void
    {
        $category = fn (string $name, ?Category $parent = null) => Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'parent_id' => $parent?->id, 'name' => $name, 'slug' => str(($parent?->slug ?? '').' '.$name)->slug()->value(), 'is_active' => true]);
        $men = $category('Men');
        $polos = $category('T-shirts & Polos', $men);
        $shirts = $category('shirts', $polos);
        $this->signIn();

        $form = Livewire::test(CreateProduct::class)
            // By name only, each right after its parent.
            ->assertFormFieldExists('category_level_0', function ($field) use ($men, $polos, $shirts) {
                $options = $field->getOptions();
                $order = array_keys($options);

                return $options[$shirts->id] === 'shirts'
                    && array_search($men->id, $order) < array_search($polos->id, $order)
                    && array_search($polos->id, $order) === array_search($shirts->id, $order) - 1;
            });

        $form->fillForm(['category_level_0' => $shirts->id, 'name' => 'Oxford Shirt', 'price_excl_tax' => 1500])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::withoutGlobalScopes()->where('name', 'Oxford Shirt')->firstOrFail();
        $this->assertSame($shirts->id, $product->category_id);
        $this->assertSame([$shirts->id], $product->categories()->pluck('categories.id')->all());
    }

    public function test_the_category_filter_on_reports_includes_the_categories_below(): void
    {
        $men = Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'name' => 'Men', 'slug' => 'men', 'is_active' => true]);
        $this->shirts->update(['parent_id' => $men->id]);
        $inShirts = $this->product();
        // Listed under Men only as an extra place; its main category is elsewhere.
        $extra = $this->product(['category_id' => $this->sale->id, 'name' => 'Gift Tie', 'slug' => 'gift-tie']);
        $extra->categories()->attach($this->shirts->id);
        $other = $this->product(['category_id' => $this->linen->id, 'name' => 'Linen Throw', 'slug' => 'linen-throw']);
        $this->signIn();

        $movement = fn (Product $p) => StockMovement::withoutGlobalScopes()->where('product_id', $p->id)->firstOrFail();
        Livewire::test(ListStockMovements::class)
            ->filterTable('category', $men->id)
            ->assertCanSeeTableRecords([$movement($inShirts), $movement($extra)])
            ->assertCanNotSeeTableRecords([$movement($other)]);

        $order = function (Product $p, string $number) {
            $order = Order::withoutGlobalScopes()->create([
                'client_id' => $this->client->id, 'template' => 'fashion', 'number' => $number, 'status' => 'confirmed',
                'email' => 'shopper@alpha.test', 'shipping_name' => 'Shopper', 'shipping_line1' => '1 Test Street', 'shipping_city' => 'Mumbai',
                'shipping_state' => 'MH', 'shipping_postal_code' => '400001', 'shipping_country' => 'India',
                'subtotal' => 100, 'total' => 100,
            ]);
            $order->items()->create(['product_id' => $p->id, 'name' => $p->name, 'price' => 100, 'qty' => 1, 'total' => 100]);

            return $order;
        };
        $a = $order($inShirts, 'A1');
        $b = $order($other, 'B1');
        Livewire::test(ListOrders::class)
            ->filterTable('category', $men->id)
            ->assertCanSeeTableRecords([$a])
            ->assertCanNotSeeTableRecords([$b]);
    }

    public function test_the_collection_is_single_and_can_be_changed_or_cleared(): void
    {
        $summer = $this->collection('Summer');
        $winter = $this->collection('Winter');
        $product = $this->product();
        $product->collections()->attach($summer->id, ['sort_order' => 3]);
        $this->signIn();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSet('data.collection', $summer->id)
            ->fillForm(['collection' => $winter->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame([$winter->id], $product->collections()->pluck('collections.id')->all());

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['collection' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(0, $product->collections()->count());
    }

    public function test_collections_are_in_the_dragged_order_then_newest_first(): void
    {
        $this->signIn();
        // No sort order box: the order is set by dragging rows in the collections list.
        Livewire::test(CreateCollection::class)->assertFormFieldDoesNotExist('sort_order');

        $old = $this->collection('Old');
        $new = $this->collection('New');
        $placed = $this->collection('Placed');
        $placed->update(['sort_order' => 1]);
        $last = $this->collection('Last');
        $last->update(['sort_order' => 2]);

        // New and Old both sit at 0 (never dragged): the newer one comes first.
        $this->assertSame(
            [$new->id, $old->id, $placed->id, $last->id],
            Collection::withoutGlobalScopes()->where('client_id', $this->client->id)->active()->pluck('id')->all(),
        );
    }

    public function test_the_collection_form_has_no_slug_box_and_the_slug_is_made_from_the_name(): void
    {
        $this->collection('Summer Edit');
        $this->signIn();

        Livewire::test(CreateCollection::class)
            ->assertFormFieldDoesNotExist('slug')
            ->fillForm(['name' => 'Summer Edit'])
            ->call('create')
            ->assertHasNoFormErrors();

        // The store already has "summer-edit", so the new one gets the next free address.
        $this->assertSame(['summer-edit', 'summer-edit-2'], Collection::withoutGlobalScopes()->where('client_id', $this->client->id)->orderBy('id')->pluck('slug')->all());
    }

    public function test_renaming_a_collection_keeps_its_page_address(): void
    {
        $summer = $this->collection('Summer');
        $this->signIn();

        Livewire::test(EditCollection::class, ['record' => $summer->getRouteKey()])
            ->assertFormFieldDoesNotExist('slug')
            ->assertSee('Page address: /collections/summer')
            ->fillForm(['name' => 'Summer Sale'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('summer', $summer->fresh()->slug, 'Links to the collection page keep working.');
    }

    public function test_adding_a_product_from_the_collection_form_moves_it_there(): void
    {
        $summer = $this->collection('Summer');
        $winter = $this->collection('Winter');
        $product = $this->product();
        $product->collections()->attach($summer->id);
        $this->signIn();

        Livewire::test(EditCollection::class, ['record' => $winter->getRouteKey()])
            ->fillForm(['products' => [$product->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$winter->id], $product->collections()->pluck('collections.id')->all());
    }

    public function test_the_collection_form_shows_its_products_in_a_multi_select(): void
    {
        $summer = $this->collection('Summer');
        $product = $this->product();
        $product->collections()->attach($summer->id);
        $this->signIn();

        // A searchable multi-select: the products already in the collection show as pills.
        Livewire::test(EditCollection::class, ['record' => $summer->getRouteKey()])
            ->assertFormFieldExists('products', fn (Select $field) => $field->isMultiple() && $field->isSearchable())
            ->assertFormSet(['products' => [$product->id]]);
    }

    public function test_keeping_the_same_collection_keeps_the_products_place_in_it(): void
    {
        $summer = $this->collection('Summer');
        $product = $this->product();
        $product->collections()->attach($summer->id, ['sort_order' => 7]);
        $this->signIn();

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Linen Shirt II'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(7, (int) $product->collections()->first()->pivot->sort_order);
    }

    public function test_writing_only_the_main_category_also_lists_the_product_under_it(): void
    {
        $product = $this->product();

        $this->assertSame([$this->shirts->id], $product->categories()->pluck('categories.id')->all());

        $product->update(['category_id' => $this->linen->id]);
        $this->assertEqualsCanonicalizing([$this->shirts->id, $this->linen->id], $product->categories()->pluck('categories.id')->all());
    }

    public function test_the_storefront_lists_the_product_under_every_category(): void
    {
        $product = $this->product();
        $product->categories()->attach($this->sale->id);
        $url = $this->client->publicUrl();

        $this->get($url.'/shop/shirts')->assertSuccessful()->assertSee('Linen Shirt');
        $this->get($url.'/shop/gifts')->assertSuccessful()->assertSee('Linen Shirt');
        $this->get($url.'/shop/linen')->assertSuccessful()->assertDontSee('Linen Shirt');
        // The shop's category filter also finds it through a category that is not its main one.
        $this->product(['category_id' => $this->linen->id, 'name' => 'Wool Coat', 'slug' => 'wool-coat']);
        $this->get($url.'/shop?category=gifts')->assertSuccessful()->assertSee('Linen Shirt')->assertDontSee('Wool Coat');
    }

    public function test_filtering_the_product_list_by_a_category_includes_the_ones_below_it(): void
    {
        $category = fn (string $name, ?Category $parent = null) => Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'parent_id' => $parent?->id, 'name' => $name, 'slug' => str(($parent?->slug ?? '').' '.$name)->slug()->value(), 'is_active' => true]);
        $men = $category('Men');
        $menShirts = $category('Shirts', $men);
        $formal = $category('Formal', $menShirts);
        $oxford = $this->product(['category_id' => $formal->id, 'name' => 'Oxford Shirt', 'slug' => 'oxford-shirt']);
        $polo = $this->product(['category_id' => $menShirts->id, 'name' => 'Polo', 'slug' => 'polo']);
        $dress = $this->product(['category_id' => $category('Dresses', $category('Women'))->id, 'name' => 'Maxi Dress', 'slug' => 'maxi-dress']);
        $this->signIn();

        Livewire::test(ListProducts::class)
            ->filterTable('categories', $men->id)
            ->assertCanSeeTableRecords([$oxford, $polo])
            ->assertCanNotSeeTableRecords([$dress])
            ->filterTable('categories', $formal->id)
            ->assertCanSeeTableRecords([$oxford])
            ->assertCanNotSeeTableRecords([$polo, $dress]);
    }

    public function test_the_category_filter_shows_the_categories_above_each_option_on_the_right(): void
    {
        $category = fn (string $name, ?Category $parent = null) => Category::withoutGlobalScopes()->create(['client_id' => $this->client->id, 'template' => 'fashion', 'parent_id' => $parent?->id, 'name' => $name, 'slug' => str(($parent?->slug ?? '').' '.$name)->slug()->value(), 'is_active' => true]);
        $men = $category('Men');
        $pants = $category('Pants', $men);
        $formal = $category('Formal', $category('Shirts', $men));
        $this->signIn();

        $page = Livewire::test(ListProducts::class);
        $options = $page->instance()->getTable()->getFilter('categories')->getOptions();

        // Still in tree order: each category followed by those below it.
        $this->assertSame('Men', $options[$men->id]);
        $this->assertSame('<span class="fi-cat-option">Pants<span class="fi-cat-option-path">Men</span></span>', $options[$pants->id]);
        $this->assertSame('<span class="fi-cat-option">Formal<span class="fi-cat-option-path">Men › Shirts</span></span>', $options[$formal->id]);
        $order = array_keys($options);
        $this->assertLessThan(array_search($pants->id, $order, true), array_search($men->id, $order, true));

        // The chip above the table is plain text, not the option's markup.
        $page->filterTable('categories', $formal->id);
        $chips = array_map(fn ($indicator) => $indicator->getLabel(), $page->instance()->getTable()->getFilter('categories')->getIndicators());
        $this->assertSame(['Category: Men › Shirts › Formal'], $chips);
    }
}
