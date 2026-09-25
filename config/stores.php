<?php

/*
|--------------------------------------------------------------------------
| Store isolation
|--------------------------------------------------------------------------
| How far apart the stores' data is kept.
|
|   shared    One database. Rows carry a `template` column and every query is scoped
|             by it (global scope + policies). This is the default and needs no setup.
|
|   database  One database per store (store_fashion, store_grocery, …). Store content
|             lives in the store's own database; platform data (users, stores, settings)
|             stays in the main one. Works on PostgreSQL and MySQL.
|
|   schema    PostgreSQL only: one schema per store inside the same database. Same
|             isolation as `database` with a single connection, easier to back up
|             together and to host on a managed instance that allows one database.
|
| Switch with STORE_ISOLATION, then run `php artisan stores:provision` (creates the
| databases/schemas and their tables) and `php artisan stores:split` (moves each store's
| existing rows across). Nothing is deleted from the main database by either command.
*/

return [
    'isolation' => env('STORE_ISOLATION', 'shared'),

    // Connection whose credentials the per-store connections are cloned from.
    'connection' => env('STORE_CONNECTION') ?: env('DB_CONNECTION', 'sqlite'),

    // Naming of the per-store database / schema, suffixed with the store slug.
    'prefix' => env('STORE_DB_PREFIX', 'store_'),

    // Tables that belong to a store. Everything else (users, storefronts, settings,
    // store locations, sessions, cache, jobs) stays in the main database.
    'tables' => [
        'products', 'categories', 'collections', 'collection_product', 'coupons',
        'orders', 'order_items', 'reviews', 'pages', 'posts', 'faqs', 'menus', 'menu_items',
        'contact_messages', 'subscribers',
        'services', 'service_areas', 'testimonials', 'projects', 'service_requests',
    ],

    // Foreign keys that would point at the main database once a store is separated.
    'cross_database_foreign_keys' => [
        'orders' => ['user_id'],
        'reviews' => ['user_id'],
        'service_requests' => ['user_id'],
    ],
];
