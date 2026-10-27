<?php

/*
|--------------------------------------------------------------------------
| Client isolation
|--------------------------------------------------------------------------
| A client is the tenant. How far apart two clients' data is kept:
|
|   database  One database per client (client_acme, client_globex, …). The client's content
|             lives in its own database; platform data (super admins, clients, their staff
|             and customers, storefronts, settings) stays in the main one. MySQL and
|             PostgreSQL. This is the recommended setting.
|
|   schema    PostgreSQL only: one schema per client inside the same database. Same
|             isolation with a single connection, easier to back up together.
|
|   shared    One database for everyone. Rows carry `client_id` and every query is scoped by
|             it (global scope + policies + per-request resolved context). Needs no setup.
|
| Whichever setting is used, the client id is written to and filtered on every content row,
| so isolation never depends on the connection alone.
|
| After switching, run `php artisan clients:provision` to create the databases and build
| each client's content.
*/

return [
    'isolation' => env('CLIENT_ISOLATION', 'shared'),

    // Connection whose credentials the per-client connections are cloned from.
    'connection' => env('CLIENT_CONNECTION') ?: env('DB_CONNECTION', 'mysql'),

    // Naming of the per-client database / schema, suffixed with the client slug.
    'prefix' => env('CLIENT_DB_PREFIX', 'client_'),

    // Product images allowed per client when neither the client nor its plan says otherwise.
    'default_image_limit' => (int) env('CLIENT_IMAGE_LIMIT', 50),

    // Tables that belong to a client. Everything else (super admins, clients, client_users,
    // customers, addresses, storefronts, settings, store locations, sessions) is platform data
    // and stays in the main database.
    'tables' => [
        'products', 'categories', 'collections', 'collection_product', 'coupons',
        'orders', 'order_items', 'reviews', 'pages', 'posts', 'faqs', 'menus', 'menu_items',
        'contact_messages', 'subscribers',
        'services', 'service_areas', 'testimonials', 'projects', 'service_requests',
    ],

    // Foreign keys that would point at the main database once a client is separated.
    'cross_database_foreign_keys' => [
        'orders' => ['customer_id', 'user_id'],
        'reviews' => ['customer_id', 'user_id'],
        'service_requests' => ['customer_id', 'user_id'],
    ],
];
