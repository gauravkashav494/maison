<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * Navigation menus for the Plumbing Services template (its own locations, so the
 * other templates' menus are untouched). Re-running resets only these menus' items.
 */
class PlumbingServicesMenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->menu('plumbing_services_header', 'Plumbing Services — Header navigation', [
            ['label' => 'Services', 'url' => '/services'],
            ['label' => 'Emergency', 'url' => '/emergency', 'is_accent' => true],
            ['label' => 'Service areas', 'url' => '/service-areas'],
            ['label' => 'Our work', 'url' => '/projects'],
            ['label' => 'About', 'url' => '/about'],
            ['label' => 'Blog', 'url' => '/journal'],
            ['label' => 'Contact', 'url' => '/contact'],
        ]);

        $this->menu('plumbing_services_footer_services', 'Plumbing Services — Footer services', [
            ['label' => 'Emergency plumbing', 'url' => '/services/emergency-plumbing'],
            ['label' => 'Leak repair', 'url' => '/services/leak-repair'],
            ['label' => 'Drain cleaning', 'url' => '/services/drain-cleaning'],
            ['label' => 'Bathroom plumbing', 'url' => '/services/bathroom-plumbing'],
            ['label' => 'Kitchen plumbing', 'url' => '/services/kitchen-plumbing'],
            ['label' => 'Water tank services', 'url' => '/services/water-tank-services'],
            ['label' => 'All services', 'url' => '/services'],
        ]);

        $this->menu('plumbing_services_footer_company', 'Plumbing Services — Footer company', [
            ['label' => 'About us', 'url' => '/about'],
            ['label' => 'Recent work', 'url' => '/projects'],
            ['label' => 'Service areas', 'url' => '/service-areas'],
            ['label' => 'Blog & guides', 'url' => '/journal'],
            ['label' => 'Careers', 'url' => '/careers'],
        ]);

        $this->menu('plumbing_services_footer_help', 'Plumbing Services — Footer help', [
            ['label' => 'Book a plumber', 'url' => '/book'],
            ['label' => 'Request a quote', 'url' => '/quote'],
            ['label' => 'My bookings', 'url' => '/bookings'],
            ['label' => 'FAQs', 'url' => '/faq'],
            ['label' => 'Contact us', 'url' => '/contact'],
        ]);

        $this->menu('plumbing_services_legal', 'Plumbing Services — Footer policies', [
            ['label' => 'Privacy policy', 'url' => '/privacy'],
            ['label' => 'Terms of service', 'url' => '/terms'],
            ['label' => 'Service warranty', 'url' => '/warranty'],
        ]);

        Menu::flush();
    }

    private function menu(string $location, string $name, array $items): void
    {
        $menu = Menu::updateOrCreate(['location' => $location], ['name' => $name, 'template' => 'plumbing-services']);
        $menu->allItems()->delete();
        foreach ($items as $i => $item) {
            $menu->allItems()->create($item + ['sort_order' => $i]);
        }
    }
}
