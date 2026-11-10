<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pages the Simple List template links to from its menus. Only creates what is missing, so a
 * page the client has already edited is left alone when this runs again.
 */
class SimplePagesSeeder extends Seeder
{
    public function run(): void
    {
        Page::firstOrCreate(['slug' => 'contact', 'storefront_template' => 'simple'], [
            'title' => 'Contact us',
            'template' => 'contact',
            'excerpt' => 'Questions about an order, a price or stock? Send us a message and we will get back to you.',
            'meta_description' => 'Get in touch about an order, a price or product availability.',
            'data' => ['subjects' => ['Order enquiry', 'Product question', 'Pricing or bulk order', 'Returns', 'Other']],
            'is_active' => true,
        ]);
    }
}
