<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/** Navigation for the Heritage Grocery template. Category menus come from the catalogue; these are the extra links. */
class HeritageMenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->menu('heritage_header', 'Heritage — Header links', [
            ['label' => 'Deals & Offers', 'url' => '/shop/sale', 'is_accent' => true],
            ['label' => 'Gift Boxes', 'url' => '/shop/gift-boxes'],
            ['label' => 'New Launches', 'url' => '/shop/new-arrivals'],
            ['label' => 'Recipes', 'url' => '/journal'],
        ]);

        $this->menu('heritage_footer_shop', 'Heritage — Footer shop', [
            ['label' => 'Rice & Grains', 'url' => '/shop/rice-grains'],
            ['label' => 'Pulses & Dals', 'url' => '/shop/pulses-dals'],
            ['label' => 'Flour & Atta', 'url' => '/shop/flour-atta'],
            ['label' => 'Spices & Masalas', 'url' => '/shop/spices-masalas'],
            ['label' => 'Oils & Ghee', 'url' => '/shop/oils-ghee'],
            ['label' => 'Dry Fruits & Nuts', 'url' => '/shop/dry-fruits-nuts'],
            ['label' => 'Tea & Beverages', 'url' => '/shop/tea-beverages'],
            ['label' => 'Gift Boxes', 'url' => '/shop/gift-boxes'],
        ]);

        $this->menu('heritage_footer_help', 'Heritage — Footer support', [
            ['label' => 'Track your order', 'url' => '/track-order'],
            ['label' => 'Shipping & delivery', 'url' => '/shipping'],
            ['label' => 'Returns & refunds', 'url' => '/returns'],
            ['label' => 'Payment options', 'url' => '/payment'],
            ['label' => 'FAQs', 'url' => '/faq'],
            ['label' => 'Contact us', 'url' => '/contact'],
        ]);

        $this->menu('heritage_footer_company', 'Heritage — Footer about', [
            ['label' => 'Our story', 'url' => '/about'],
            ['label' => 'Our farmers', 'url' => '/about#farmers'],
            ['label' => 'Stores', 'url' => '/stores'],
            ['label' => 'Careers', 'url' => '/careers'],
            ['label' => 'Recipes & stories', 'url' => '/journal'],
            ['label' => 'Gift cards', 'url' => '/gift-cards'],
        ]);

        $this->menu('heritage_legal', 'Heritage — Legal links', [
            ['label' => 'Privacy policy', 'url' => '/privacy'],
            ['label' => 'Terms & conditions', 'url' => '/terms'],
            ['label' => 'Cookie policy', 'url' => '/cookies'],
        ]);

        Menu::flush();
    }

    private function menu(string $location, string $name, array $items): void
    {
        $menu = Menu::updateOrCreate(['location' => $location], ['name' => $name, 'template' => 'heritage']);
        $menu->allItems()->delete();
        foreach ($items as $i => $item) {
            $menu->allItems()->create($item + ['sort_order' => $i]);
        }
    }
}
