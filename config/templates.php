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

    'templates' => [
        App\Templates\Fashion\FashionTemplate::class,
        App\Templates\Grocery\GroceryTemplate::class,
        App\Templates\Heritage\HeritageTemplate::class,
    ],
];
