<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Support\Media;
use Illuminate\Database\Seeder;

/**
 * Sample store locations for a template's "Our stores" page, written in that store's own voice.
 * Each template's install() runs it for its id, so a new client starts with locations that
 * fit its store; the client then edits or replaces them under Content → Store locations.
 *
 * Templates without a stores page (Plumbing Services, Simple List) have none.
 */
class StoreLocationsSeeder extends Seeder
{
    public function run(?string $template = null): void
    {
        foreach (self::for($template) as $i => $location) {
            Store::updateOrCreate(
                ['name' => $location['name'], 'template' => $template],
                $location + ['sort_order' => $i, 'is_active' => true],
            );
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function for(?string $template): array
    {
        $photo = fn (string $id) => Media::unsplash($id, 1200, '&h=900');

        return match ($template) {
            'fashion' => [
                ['name' => 'Bengaluru Flagship', 'address' => '12 Lavelle Road, Ashok Nagar', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560001', 'phone' => '+91 80 4500 1200', 'email' => 'bengaluru@maisonelan.com', 'hours' => "Mon–Sat 10:30–20:00\nSun 11:00–18:00", 'lat' => 12.9716, 'lng' => 77.5946, 'image' => $photo('1445205170230-053b83016050'), 'maps_url' => 'https://maps.google.com/?q=Lavelle+Road+Bengaluru'],
                ['name' => 'Mumbai Boutique', 'address' => 'Kala Ghoda, Fort', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'postal_code' => '400001', 'phone' => '+91 22 4500 1300', 'email' => 'mumbai@maisonelan.com', 'hours' => "Mon–Sat 11:00–20:00\nSun 12:00–18:00", 'lat' => 18.9282, 'lng' => 72.8318, 'image' => $photo('1551232864-3f0890e580d9'), 'maps_url' => 'https://maps.google.com/?q=Kala+Ghoda+Mumbai'],
                ['name' => 'New Delhi Boutique', 'address' => 'The Chanakya, Chanakyapuri', 'city' => 'New Delhi', 'state' => 'Delhi', 'postal_code' => '110021', 'phone' => '+91 11 4500 1400', 'email' => 'delhi@maisonelan.com', 'hours' => 'Mon–Sun 11:00–21:00', 'lat' => 28.5985, 'lng' => 77.1866, 'image' => $photo('1558769132-cb1aea458c5e'), 'maps_url' => 'https://maps.google.com/?q=The+Chanakya+New+Delhi'],
            ],
            'heritage' => [
                ['name' => 'Indiranagar Pantry', 'address' => '480, 100 Feet Road, HAL 2nd Stage, Indiranagar', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560038', 'phone' => '+91 80 4110 2200', 'email' => 'indiranagar@annapurnaorganics.in', 'hours' => "Mon–Sat 08:00–21:00\nSun 08:00–14:00\nTasting counter daily 11:00–19:00", 'lat' => 12.9719, 'lng' => 77.6412, 'image' => $photo('1506368249639-73a05d6f6488'), 'maps_url' => 'https://maps.google.com/?q=100+Feet+Road+Indiranagar+Bengaluru'],
                ['name' => 'Bandra Store', 'address' => '24 Hill Road, Bandra West', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'postal_code' => '400050', 'phone' => '+91 22 4110 2300', 'email' => 'bandra@annapurnaorganics.in', 'hours' => "Mon–Sun 08:30–21:30", 'lat' => 19.0544, 'lng' => 72.8340, 'image' => $photo('1471943311424-646960669fbc'), 'maps_url' => 'https://maps.google.com/?q=Hill+Road+Bandra+West+Mumbai'],
                ['name' => 'Koregaon Park Store', 'address' => 'Lane 6, North Main Road, Koregaon Park', 'city' => 'Pune', 'state' => 'Maharashtra', 'postal_code' => '411001', 'phone' => '+91 20 4110 2400', 'email' => 'pune@annapurnaorganics.in', 'hours' => "Mon–Sat 08:00–21:00\nSun 09:00–14:00", 'lat' => 18.5362, 'lng' => 73.8940, 'image' => $photo('1464226184884-fa280b87c399'), 'maps_url' => 'https://maps.google.com/?q=North+Main+Road+Koregaon+Park+Pune'],
            ],
            'grocery' => [
                ['name' => 'Koramangala Store', 'address' => '80 Feet Road, 4th Block, Koramangala', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560034', 'phone' => '+91 80 4720 1100', 'email' => 'koramangala@maisonfresh.in', 'hours' => "Mon–Sun 06:30–23:00\nOrder pick-up all day", 'lat' => 12.9352, 'lng' => 77.6245, 'image' => $photo('1542838132-92c53300491e'), 'maps_url' => 'https://maps.google.com/?q=80+Feet+Road+Koramangala+Bengaluru'],
                ['name' => 'HSR Layout Store', 'address' => '27th Main Road, Sector 1, HSR Layout', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560102', 'phone' => '+91 80 4720 1200', 'email' => 'hsr@maisonfresh.in', 'hours' => 'Mon–Sun 06:30–23:00', 'lat' => 12.9116, 'lng' => 77.6474, 'image' => $photo('1488459716781-31db52582fe9'), 'maps_url' => 'https://maps.google.com/?q=27th+Main+Road+HSR+Layout+Bengaluru'],
                ['name' => 'Whitefield Store', 'address' => 'ITPL Main Road, Whitefield', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'postal_code' => '560066', 'phone' => '+91 80 4720 1300', 'email' => 'whitefield@maisonfresh.in', 'hours' => "Mon–Sun 07:00–22:30", 'lat' => 12.9698, 'lng' => 77.7500, 'image' => $photo('1583258292688-d0213dc5a3a8'), 'maps_url' => 'https://maps.google.com/?q=ITPL+Main+Road+Whitefield+Bengaluru'],
            ],
            'plumbing' => [
                ['name' => 'Kirti Nagar Showroom & Counter', 'address' => 'Plot 14, Kirti Nagar Industrial Area', 'city' => 'New Delhi', 'state' => 'Delhi', 'postal_code' => '110015', 'phone' => '+91 11 4930 5500', 'email' => 'delhi@plumbkart.in', 'hours' => "Mon–Sat 09:30–19:30\nSun closed", 'lat' => 28.6519, 'lng' => 77.1450, 'image' => $photo('1584622650111-993a426fbf0a'), 'maps_url' => 'https://maps.google.com/?q=Kirti+Nagar+Industrial+Area+New+Delhi'],
                ['name' => 'Chakan Warehouse (pick-up & returns)', 'address' => 'Gat No. 112, Chakan MIDC Phase II', 'city' => 'Pune', 'state' => 'Maharashtra', 'postal_code' => '410501', 'phone' => '+91 20 4930 5600', 'email' => 'pune@plumbkart.in', 'hours' => "Mon–Sat 09:00–18:00\nBulk orders by appointment", 'lat' => 18.7606, 'lng' => 73.8636, 'image' => $photo('1545193329-4a052e14eb8f'), 'maps_url' => 'https://maps.google.com/?q=Chakan+MIDC+Pune'],
                ['name' => 'Secunderabad Trade Counter', 'address' => 'Rashtrapati Road, near Clock Tower', 'city' => 'Hyderabad', 'state' => 'Telangana', 'postal_code' => '500003', 'phone' => '+91 40 4930 5700', 'email' => 'hyderabad@plumbkart.in', 'hours' => "Mon–Sat 09:30–19:00", 'lat' => 17.4399, 'lng' => 78.4983, 'image' => $photo('1676210134188-4c05dd172f89'), 'maps_url' => 'https://maps.google.com/?q=Rashtrapati+Road+Secunderabad'],
            ],
            default => [],
        };
    }
}
