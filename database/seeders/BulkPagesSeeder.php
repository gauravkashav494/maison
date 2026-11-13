<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pages the Bulk Order template links to from its menus. Only creates what is missing, so a
 * page the client has already edited is left alone when this runs again.
 */
class BulkPagesSeeder extends Seeder
{
    public function run(): void
    {
        Page::firstOrCreate(['slug' => 'contact', 'storefront_template' => 'bulk'], [
            'title' => 'Contact us',
            'template' => 'contact',
            'excerpt' => 'Opening a trade account, a large order or a delivery question? Send us a message and the trade desk will get back to you.',
            'meta_description' => 'Get in touch about an order, a price or product availability.',
            'data' => ['subjects' => ['Order enquiry', 'Product question', 'Trade account or pricing', 'Returns', 'Other']],
            'is_active' => true,
        ]);
    }
}
