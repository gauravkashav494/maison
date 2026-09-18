<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Navigation menus for the Indian Grocery template (its own locations, so the
 * Fashion menus are untouched). Re-running resets only these menus' items.
 */
class GroceryMenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->menu('grocery_header', 'Grocery — Header quick links', [
            ['label' => 'Offers', 'url' => '/shop/sale', 'is_accent' => true],
            ['label' => 'Bestsellers', 'url' => '/shop/best-sellers'],
            ['label' => 'New in store', 'url' => '/shop/new-arrivals'],
            ['label' => 'Combos', 'url' => '/collections'],
            ['label' => 'Recipes & tips', 'url' => '/journal'],
        ]);

        $this->menu('grocery_footer_categories', 'Grocery — Footer categories', [
            ['label' => 'Fruits & Vegetables', 'url' => '/shop/fruits-vegetables'],
            ['label' => 'Dairy, Bread & Eggs', 'url' => '/shop/dairy-bread-eggs'],
            ['label' => 'Atta, Rice & Dal', 'url' => '/shop/atta-rice-dal'],
            ['label' => 'Oils, Ghee & Masalas', 'url' => '/shop/oils-ghee-masalas'],
            ['label' => 'Snacks & Namkeen', 'url' => '/shop/snacks-namkeen'],
            ['label' => 'Tea, Coffee & Beverages', 'url' => '/shop/tea-coffee-beverages'],
            ['label' => 'Household & Cleaning', 'url' => '/shop/household-cleaning'],
            ['label' => 'Baby Care', 'url' => '/shop/baby-care'],
        ]);

        $this->menu('grocery_footer_help', 'Grocery — Footer help', [
            ['label' => 'Track your order', 'url' => '/track-order'],
            ['label' => 'Delivery areas & timings', 'url' => '/shipping'],
            ['label' => 'Returns & refunds', 'url' => '/returns'],
            ['label' => 'FAQs', 'url' => '/faq'],
            ['label' => 'Contact us', 'url' => '/contact'],
        ]);

        $this->menu('grocery_footer_company', 'Grocery — Footer company', [
            ['label' => 'About us', 'url' => '/about'],
            ['label' => 'Our stores', 'url' => '/stores'],
            ['label' => 'Careers', 'url' => '/careers'],
            ['label' => 'Recipes & tips', 'url' => '/journal'],
            ['label' => 'Gift cards', 'url' => '/gift-cards'],
        ]);

        $this->menu('grocery_legal', 'Grocery — Legal links', [
            ['label' => 'Privacy policy', 'url' => '/privacy'],
            ['label' => 'Terms & conditions', 'url' => '/terms'],
            ['label' => 'Cookie policy', 'url' => '/cookies'],
        ]);

        Menu::flush();
    }

    private function menu(string $location, string $name, array $items): void
    {
        $menu = Menu::updateOrCreate(['location' => $location], ['name' => $name, 'template' => 'grocery']);
        $menu->allItems()->delete();
        foreach ($items as $i => $item) {
            $menu->allItems()->create($item + ['sort_order' => $i]);
        }
    }
}
