<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Support\Media;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        MenuItem::query()->delete();

        $header = Menu::updateOrCreate(['location' => 'header'], ['name' => 'Header']);
        $this->items($header, [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Shop', 'url' => '/shop', 'children' => [
                ['group' => 'Categories', 'label' => 'Clothing', 'url' => '/shop/clothing'],
                ['group' => 'Categories', 'label' => 'Perfumes', 'url' => '/shop/perfumes'],
                ['group' => 'Categories', 'label' => 'Watches', 'url' => '/shop/watches'],
                ['group' => 'Categories', 'label' => 'Bags', 'url' => '/shop/bags'],
                ['group' => 'Categories', 'label' => 'Belts', 'url' => '/shop/belts'],
                ['group' => 'Categories', 'label' => 'Accessories', 'url' => '/shop/accessories'],
                ['group' => 'Clothing', 'label' => 'Coats & Jackets', 'url' => '/shop/outerwear'],
                ['group' => 'Clothing', 'label' => 'Tailoring', 'url' => '/shop/tailoring'],
                ['group' => 'Clothing', 'label' => 'Knitwear', 'url' => '/shop/knitwear'],
                ['group' => 'Clothing', 'label' => 'Shirts', 'url' => '/shop/shirts'],
                ['group' => 'Clothing', 'label' => 'Trousers', 'url' => '/shop/trousers'],
                ['group' => 'Clothing', 'label' => 'Footwear', 'url' => '/shop/footwear'],
                ['group' => 'Featured', 'label' => 'New Arrivals', 'url' => '/shop/new-arrivals'],
                ['group' => 'Featured', 'label' => 'Best Sellers', 'url' => '/shop/best-sellers'],
                ['group' => 'Featured', 'label' => 'Sale', 'url' => '/shop/sale', 'is_accent' => true],
                ['group' => 'Featured', 'label' => 'Gift Cards', 'url' => '/gift-cards'],
                ['group' => 'Tiles', 'label' => 'Autumn Outerwear', 'eyebrow' => 'Just in', 'url' => '/shop/new-arrivals', 'image' => Media::unsplash('1539533018447-63fcce2678e3', 700, '&h=900')],
                ['group' => 'Tiles', 'label' => 'The Amber Edit', 'eyebrow' => 'Fragrance', 'url' => '/collections/fragrance-edit', 'image' => Media::unsplash('1622618991746-fe6004db3a47', 700, '&h=900')],
            ]],
            ['label' => 'Collections', 'url' => '/collections', 'children' => [
                ['group' => 'Collections', 'label' => 'New Season', 'url' => '/collections/new-season'],
                ['group' => 'Collections', 'label' => 'Essentials', 'url' => '/collections/essentials'],
                ['group' => 'Collections', 'label' => 'Signature Collection', 'url' => '/collections/signature'],
                ['group' => 'Collections', 'label' => 'Fragrance Edit', 'url' => '/collections/fragrance-edit'],
                ['group' => 'Collections', 'label' => 'Timepieces', 'url' => '/collections/timepieces'],
                ['group' => 'Edits', 'label' => 'Leather Collection', 'url' => '/collections/leather'],
                ['group' => 'Edits', 'label' => 'Weekend Edit', 'url' => '/collections/weekend-edit'],
                ['group' => 'Edits', 'label' => 'Evening Edit', 'url' => '/collections/evening-edit'],
                ['group' => 'Edits', 'label' => 'Gift Collection', 'url' => '/collections/gift-collection'],
                ['group' => 'Tiles', 'label' => 'New Season', 'eyebrow' => 'Autumn / Winter', 'url' => '/collections/new-season', 'image' => Media::unsplash('1526413232644-8a40f03cc03b', 700, '&h=900')],
                ['group' => 'Tiles', 'label' => 'Signature Collection', 'eyebrow' => 'Campaign', 'url' => '/collections/signature', 'image' => Media::unsplash('1610652492500-ded49ceeb378', 700, '&h=900')],
                ['group' => 'Tiles', 'label' => 'Timepieces', 'eyebrow' => 'Watches', 'url' => '/collections/timepieces', 'image' => Media::unsplash('1619134778706-7015533a6150', 700, '&h=900')],
            ]],
            ['label' => 'About', 'url' => '/about'],
        ]);

        $this->items(Menu::updateOrCreate(['location' => 'footer_shop'], ['name' => 'Footer — Shop']), [
            ['label' => 'Clothing', 'url' => '/shop/clothing'],
            ['label' => 'Perfumes', 'url' => '/shop/perfumes'],
            ['label' => 'Watches', 'url' => '/shop/watches'],
            ['label' => 'Bags', 'url' => '/shop/bags'],
            ['label' => 'Belts', 'url' => '/shop/belts'],
            ['label' => 'Accessories', 'url' => '/shop/accessories'],
            ['label' => 'New Arrivals', 'url' => '/shop/new-arrivals'],
            ['label' => 'Best Sellers', 'url' => '/shop/best-sellers'],
            ['label' => 'Sale', 'url' => '/shop/sale'],
        ]);

        $this->items(Menu::updateOrCreate(['location' => 'footer_collections'], ['name' => 'Footer — Collections']), [
            ['label' => 'New Season', 'url' => '/collections/new-season'],
            ['label' => 'Essentials', 'url' => '/collections/essentials'],
            ['label' => 'Signature Collection', 'url' => '/collections/signature'],
            ['label' => 'Fragrance Edit', 'url' => '/collections/fragrance-edit'],
            ['label' => 'Timepieces', 'url' => '/collections/timepieces'],
            ['label' => 'Leather Collection', 'url' => '/collections/leather'],
            ['label' => 'Weekend Edit', 'url' => '/collections/weekend-edit'],
        ]);

        $this->items(Menu::updateOrCreate(['location' => 'footer_about'], ['name' => 'Footer — About']), [
            ['label' => 'Our Story', 'url' => '/about'],
            ['label' => 'Journal', 'url' => '/journal'],
            ['label' => 'Sustainability', 'url' => '/about#sustainability'],
            ['label' => 'Careers', 'url' => '/careers'],
            ['label' => 'Store Locator', 'url' => '/stores'],
            ['label' => 'Gift Cards', 'url' => '/gift-cards'],
        ]);

        $this->items(Menu::updateOrCreate(['location' => 'footer_service'], ['name' => 'Footer — Customer Service']), [
            ['label' => 'Contact Us', 'url' => '/contact'],
            ['label' => 'FAQ', 'url' => '/faq'],
            ['label' => 'Shipping Information', 'url' => '/shipping'],
            ['label' => 'Returns & Exchanges', 'url' => '/returns'],
            ['label' => 'Order Tracking', 'url' => '/track-order'],
            ['label' => 'Size Guide', 'url' => '/size-guide'],
            ['label' => 'Care Guide', 'url' => '/care-guide'],
            ['label' => 'Payment Information', 'url' => '/payment'],
        ]);

        $this->items(Menu::updateOrCreate(['location' => 'legal'], ['name' => 'Footer — Legal']), [
            ['label' => 'Privacy Policy', 'url' => '/privacy'],
            ['label' => 'Terms & Conditions', 'url' => '/terms'],
            ['label' => 'Cookie Policy', 'url' => '/cookies'],
            ['label' => 'Accessibility', 'url' => '/accessibility'],
            ['label' => 'Disclaimer', 'url' => '/disclaimer'],
        ]);

        Menu::flush();
    }

    private function items(Menu $menu, array $items, ?int $parentId = null): void
    {
        foreach ($items as $i => $item) {
            $children = $item['children'] ?? [];
            unset($item['children']);
            $row = $menu->allItems()->create($item + ['parent_id' => $parentId, 'sort_order' => $i]);
            if ($children) {
                $this->items($menu, $children, $row->id);
            }
        }
    }
}
