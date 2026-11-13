<?php

/*
|--------------------------------------------------------------------------
| Storefront templates
|--------------------------------------------------------------------------
| Every template is a class extending App\Templates\Template. It declares where
| its Blade views live, which Vite assets it loads, which menu locations and
| settings groups it owns, and the admin pages that configure it. The backend
| (routes, controllers, models, cart, checkout, SEO) is shared by all templates.
|
| Register a new template by adding its class here and creating its view folder.
*/

return [
    // Used when nothing has been activated yet (settings "appearance.active_template").
    'default' => 'fashion',

    // Every client and every store demo is on <slug>.<base_domain> (or its own custom domain); this
    // needs a wildcard DNS record and certificate. The base domain itself is the platform's address
    // (Super Admin at /login). Defaults to APP_URL's host.
    'base_domain' => env('STORE_BASE_DOMAIN'),

    // Public theme previews live at <prefix><template>.<base_domain>, e.g. theme-fashion; client
    // names may not start with it. Built with `php artisan themes:build` or Appearance → Templates.
    'theme_prefix' => 'theme-',


    'templates' => [
        App\Templates\Fashion\FashionTemplate::class,
        App\Templates\Grocery\GroceryTemplate::class,
        App\Templates\Heritage\HeritageTemplate::class,
        App\Templates\Plumbing\PlumbingTemplate::class,
        App\Templates\PlumbingServices\PlumbingServicesTemplate::class,
        App\Templates\Simple\SimpleTemplate::class,
        App\Templates\Bulk\BulkTemplate::class,
    ],
];
