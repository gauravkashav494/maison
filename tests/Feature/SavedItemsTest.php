<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Storefront;
use App\Support\SavedItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saved items keep what the shopper saved: a product with no size chosen, or exactly one size.
 * The Saved items page shows a saved size with that size's price, stock and photo, and adds
 * exactly it; a size that is no longer sold stays on the list, marked so.
 */
class SavedItemsTest extends TestCase
{
    use RefreshDatabase;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'starter'], ['name' => 'Starter', 'price_label' => '₹X', 'image_limit' => 50, 'is_default' => true]);
        $store = Storefront::create(['name' => 'Grocery', 'slug' => 'grocery', 'template' => 'grocery', 'is_active' => true]);
        $this->client = Client::create(['name' => 'Alpha', 'subdomain' => 'alpha', 'storefront_id' => $store->id, 'plan_id' => $plan->id, 'is_active' => true]);
    }

    private function product(string $name, array $attributes = []): Product
    {
        return Product::withoutGlobalScopes()->create($attributes + [
            'client_id' => $this->client->id, 'template' => 'grocery', 'name' => $name,
            'slug' => str($name)->slug()->value(), 'sku' => strtoupper(str($name)->slug()->value()),
            'price' => 100, 'stock' => 10, 'is_active' => true,
        ]);
    }

    /** Basmati rice in 1 kg and 5 kg packs, each with its own price and stock; one colour only. */
    private function rice(): Product
    {
        return $this->product('Basmati rice', [
            'has_variants' => true,
            'variant_attributes' => [
                ['name' => 'Size', 'values' => ['1 kg', '5 kg']],
                ['name' => 'Colour', 'values' => ['White']],
            ],
            'variants' => [
                ['options' => ['Size' => '1 kg', 'Colour' => 'White'], 'sku' => 'RICE-1', 'price' => 150, 'compare_at_price' => 180, 'stock' => 4],
                ['options' => ['Size' => '5 kg', 'Colour' => 'White'], 'sku' => 'RICE-5', 'price' => 650, 'stock' => 0],
            ],
        ]);
    }

    private function items(array $entries)
    {
        return $this->withSession([Client::SESSION_KEY => 'alpha'])->getJson('/api/products?items='.urlencode(json_encode($entries)));
    }

    public function test_entries_are_read_from_old_id_lists_and_new_entries_alike(): void
    {
        $this->assertSame([['id' => 3, 'options' => []], ['id' => 7, 'options' => []]], SavedItems::parse('3,7,3,nope'));

        // The same size however its name is spelt counts once; empty options mean the product.
        $this->assertSame(
            [['id' => 5, 'options' => ['Size' => 'M']], ['id' => 5, 'options' => []], ['id' => 9, 'options' => ['Colour' => 'Red', 'Size' => 'L']]],
            SavedItems::parse(json_encode([
                ['id' => 5, 'options' => ['Size' => 'M']], ['id' => 5, 'options' => ['size' => 'm']], 5,
                ['id' => 5, 'options' => ['Size' => '']], ['id' => 9, 'options' => ['Colour' => 'Red', 'Size' => 'L']],
                ['id' => 9, 'options' => ['Size' => 'L', 'Color' => 'red']], ['id' => -1], 'x', ['options' => []],
            ])),
        );

        $this->assertSame([3, ['id' => 5, 'options' => ['Size' => 'M']]], SavedItems::forStorage(SavedItems::parse(json_encode([3, ['id' => 5, 'options' => ['Size' => 'M']]]))));
        $this->assertCount(SavedItems::MAX, SavedItems::parse(implode(',', range(1, 150))));
    }

    public function test_a_product_card_names_the_options_a_shopper_picks(): void
    {
        // Colour has one value, so it is not a choice: a heart saves only the size.
        $this->assertSame([['name' => 'Size', 'values' => ['1 kg', '5 kg']]], $this->rice()->toCard()['choices']);
        $this->assertSame([], $this->product('Salt')->toCard()['choices']);
    }

    public function test_the_saved_items_list_shows_each_saved_size_as_saved(): void
    {
        $rice = $this->rice();
        $salt = $this->product('Salt');

        $cards = $this->items([['id' => $rice->id, 'options' => ['Size' => '1 kg']], $salt->id, ['id' => $rice->id, 'options' => ['size' => '5 KG']], 999999])
            ->assertOk()->json();

        $this->assertCount(3, $cards, 'In the order saved; a product that is not there is left out.');
        [$small, $plain, $large] = $cards;

        // A size: that size's price and "was" price, only that size to add, its full choice for the cart.
        $this->assertSame([$rice->id, 150, money(180), ['1 kg'], '1 kg', true], [$small['id'], (int) $small['price'], $small['compare_at_price_formatted'], $small['sizes'], $small['unit'], $small['in_stock']]);
        $this->assertSame(['Size' => '1 kg'], $small['saved']['chosen']);
        $this->assertSame(['Size' => '1 kg', 'Colour' => 'White'], $small['saved']['options']);
        $this->assertSame('Size: 1 kg', $small['saved']['label']);
        $this->assertTrue($small['saved']['available']);

        // Spelt differently when saved, still found; out of stock is not the same as gone.
        $this->assertSame(['Size: 5 kg', true, false], [$large['saved']['label'], $large['saved']['available'], $large['in_stock']]);
        $this->assertNotSame($small['saved']['key'], $large['saved']['key']);

        // A product saved without a size is its usual card.
        $this->assertSame([$salt->id, null, null], [$plain['id'], $plain['saved']['chosen'], $plain['saved']['label']]);
        $this->assertTrue($plain['in_stock']);

        // What the card adds is what the cart takes.
        $this->withSession([Client::SESSION_KEY => 'alpha'])->postJson('/cart/items', ['product_id' => $rice->id, 'options' => $small['saved']['options']])
            ->assertSuccessful()->assertJsonFragment(['variant_sku' => 'RICE-1']);
    }

    public function test_a_saved_size_that_is_no_longer_sold_stays_on_the_list_marked_so(): void
    {
        $rice = $this->rice();
        $variants = $rice->variants;
        $variants[1]['is_active'] = false;
        $rice->update(['variants' => $variants]);

        $gone = $this->items([['id' => $rice->id, 'options' => ['Size' => '5 kg']]])->json(0);
        $this->assertSame([false, false, 'Size: 5 kg', null], [$gone['saved']['available'], $gone['in_stock'], $gone['saved']['label'], $gone['saved']['options']]);
        $this->assertSame(['Size' => '5 kg'], $gone['saved']['chosen'], 'Its heart can still take it off the list.');

        // A size that was never offered is the same.
        $this->assertFalse($this->items([['id' => $rice->id, 'options' => ['Size' => '2 kg']]])->json('0.saved.available'));

        // Hidden products are left out altogether.
        $rice->update(['is_active' => false]);
        $this->assertSame([], $this->items([['id' => $rice->id, 'options' => ['Size' => '1 kg']]])->json());
    }
}
