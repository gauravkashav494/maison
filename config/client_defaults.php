<?php

/*
|--------------------------------------------------------------------------
| Starting values for a client's own settings
|--------------------------------------------------------------------------
|
| Brand, contact, SEO and checkout ("site", "seo", "checkout") belong to each client. Until a
| client saves its Site settings, its storefront and its Site settings form use these values,
| never another store's or the platform's. ":name" is replaced with the client's name.
|
*/

return [
    'site' => [
        'name' => ':name',
        'logo_primary' => ':name',
        'logo_accent' => null,
        'tagline' => null,
        'description' => null,
        'announcement_text' => null,
        'announcement_text_short' => null,
        'announcement_link_label' => null,
        'announcement_link_url' => null,
        'free_shipping_threshold' => null,
        'contact_email' => null,
        'contact_phone' => null,
        'contact_hours' => null,
        'social_instagram' => null,
        'social_pinterest' => null,
        'social_facebook' => null,
        'social_youtube' => null,
        'payment_methods' => ['Visa', 'Mastercard', 'UPI', 'RuPay'],
        'footer_blurb' => null,
        'footer_newsletter_heading' => null,
        'page_header_image' => null,
    ],

    'seo' => [
        'default_title' => ':name',
        'title_suffix' => ' — :name',
        'default_description' => null,
        'default_og_image' => null,
        'google_site_verification' => null,
        'gtag_id' => null,
        'robots_extra' => null,
        // New stores stay out of search engines until the client switches this off.
        'discourage_indexing' => true,
    ],

    'checkout' => [
        'shipping_methods' => [
            ['code' => 'standard', 'name' => 'Standard delivery', 'description' => 'Tracked courier, 3–5 business days', 'cost' => 0, 'free_over' => null, 'eta' => '3–5 business days'],
            ['code' => 'express', 'name' => 'Express delivery', 'description' => 'Priority courier, 1–2 business days', 'cost' => 150, 'free_over' => null, 'eta' => '1–2 business days'],
        ],
        'payment_methods' => ['card', 'upi', 'wallet', 'netbanking', 'cod'],
        'cod_fee' => 0,
        'tax_rate' => 0,
        'tax_label' => 'GST',
        'gift_card_amounts' => [500, 1000, 2500, 5000],
    ],
];
