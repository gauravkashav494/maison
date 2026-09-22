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

    // Every store is public on <slug>.<base_domain> (or its own custom domain). The base domain
    // itself serves the template activated under Appearance → Templates. Defaults to APP_URL's host.
    'base_domain' => env('STORE_BASE_DOMAIN'),

    // How store links are built: 'subdomain' (<slug>.<base_domain>, needs wildcard DNS + SSL) or
    // 'path' (<APP_URL>/store/<slug> — works on any host: the link remembers the store in a cookie).
    // Both entry methods are always accepted; this only decides which address is shown to share.
    'store_urls' => env('STORE_URL_MODE', env('STORE_BASE_DOMAIN') ? 'subdomain' : 'path'),

    'templates' => [
        App\Templates\Fashion\FashionTemplate::class,
        App\Templates\Grocery\GroceryTemplate::class,
        App\Templates\Heritage\HeritageTemplate::class,
        App\Templates\Plumbing\PlumbingTemplate::class,
        App\Templates\PlumbingServices\PlumbingServicesTemplate::class,
    ],
];
