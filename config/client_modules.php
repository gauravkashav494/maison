<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Faq;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceArea;
use App\Models\ServiceRequest;
use App\Models\Store;
use App\Models\Subscriber;
use App\Models\Tax;
use App\Models\Testimonial;

/*
|--------------------------------------------------------------------------
| Client modules and permissions
|--------------------------------------------------------------------------
| The single list of what a client can be given. A SuperAdmin switches modules on per client;
| a Client Admin builds roles from their actions, named "<module>.<action>" (orders.view).
|
| A module marked 'core' is one the store cannot run without: it is always on for the client and
| cannot be switched off, so a Client Admin is never left with an empty panel.
|
| 'storefront' says what switching a module off does to the public store (App\Clients\StoreFeatures):
|   hide    its records read as empty there, so every section, list and link built from them goes;
|   routes  route names (wildcards allowed) that answer 404, forms included;
|   pages   CMS page templates (Page::TEMPLATES) that answer 404, e.g. the FAQ page.
| A module without it (Pages, Navigation) only leaves the admin: the store keeps what it has.
| Templates hide their fixed links and forms with @module('reviews') ... @endmodule.
|
| A module or action missing from this list is refused, so forgetting to add a new screen here
| fails closed. ClientPermissionsTest checks that every resource on the client panel has an entry.
*/

return [
    'modules' => [
        'orders' => ['label' => 'Orders', 'group' => 'Sales', 'model' => Order::class, 'actions' => ['view', 'update'], 'core' => true],
        'customers' => ['label' => 'Customers', 'group' => 'Sales', 'model' => Customer::class, 'core' => true],

        'products' => ['label' => 'Products', 'group' => 'Catalogue', 'model' => Product::class, 'core' => true],
        'categories' => ['label' => 'Categories', 'group' => 'Catalogue', 'model' => Category::class, 'core' => true],
        'collections' => ['label' => 'Collections', 'group' => 'Catalogue', 'model' => Collection::class,
            'storefront' => ['hide' => true, 'routes' => ['collections.*']]],
        // Switching it off only leaves the admin: products keep their brand name, typed in by hand.
        'brands' => ['label' => 'Brands', 'group' => 'Catalogue', 'model' => Brand::class],
        // Every product picks its tax (name, rate), so the tax master is always on.
        'taxes' => ['label' => 'Taxes', 'group' => 'Catalogue', 'model' => Tax::class, 'core' => true],
        'reviews' => ['label' => 'Reviews', 'group' => 'Catalogue', 'model' => Review::class, 'actions' => ['view', 'update', 'delete'],
            'storefront' => ['hide' => true, 'routes' => ['products.reviews.store']]],

        'services' => ['label' => 'Services', 'group' => 'Services', 'model' => Service::class, 'core' => true],
        'service_areas' => ['label' => 'Service areas', 'group' => 'Services', 'model' => ServiceArea::class,
            'storefront' => ['hide' => true, 'routes' => ['areas.*']]],
        'service_requests' => ['label' => 'Bookings & quotes', 'group' => 'Services', 'model' => ServiceRequest::class, 'core' => true],
        'projects' => ['label' => 'Projects', 'group' => 'Services', 'model' => Project::class,
            'storefront' => ['hide' => true, 'routes' => ['projects.*']]],
        'testimonials' => ['label' => 'Testimonials', 'group' => 'Services', 'model' => Testimonial::class,
            'storefront' => ['hide' => true]],

        'pages' => ['label' => 'Pages', 'group' => 'Content', 'model' => Page::class],
        'posts' => ['label' => 'Journal', 'group' => 'Content', 'model' => Post::class,
            'storefront' => ['hide' => true, 'routes' => ['journal.*']]],
        'faqs' => ['label' => 'FAQs', 'group' => 'Content', 'model' => Faq::class,
            'storefront' => ['hide' => true, 'pages' => ['faq']]],
        'menus' => ['label' => 'Navigation', 'group' => 'Content', 'model' => Menu::class],
        'stores' => ['label' => 'Store locations', 'group' => 'Content', 'model' => Store::class,
            'storefront' => ['hide' => true, 'pages' => ['stores']]],

        'coupons' => ['label' => 'Promo codes', 'group' => 'Marketing', 'model' => Coupon::class,
            'storefront' => ['hide' => true, 'routes' => ['cart.coupon', 'cart.coupon.remove']]],
        'subscribers' => ['label' => 'Subscribers', 'group' => 'Marketing', 'model' => Subscriber::class,
            'storefront' => ['routes' => ['newsletter']]],
        'messages' => ['label' => 'Messages', 'group' => 'Marketing', 'model' => ContactMessage::class, 'actions' => ['view', 'update', 'delete'],
            'storefront' => ['routes' => ['contact.store', 'services.contact']]],

        'appearance' => ['label' => 'Store settings', 'group' => 'Appearance', 'model' => null, 'actions' => ['update'], 'core' => true],
    ],

    'action_labels' => ['view' => 'View', 'create' => 'Add', 'update' => 'Edit', 'delete' => 'Delete'],

    // Actions of a module that does not list its own.
    'default_actions' => ['view', 'create', 'update', 'delete'],

    // Ready-made roles every client starts with. '*' grants every action of the module.
    'default_roles' => [
        'Manager' => [
            'description' => 'Runs the store day to day. Everything except staff accounts and roles.',
            'permissions' => ['*' => '*'],
        ],
        'Catalogue' => [
            'description' => 'Keeps products or services up to date.',
            'permissions' => [
                'products' => '*', 'categories' => '*', 'collections' => '*', 'brands' => '*', 'taxes' => '*', 'reviews' => '*',
                'services' => '*', 'service_areas' => '*', 'projects' => '*',
            ],
        ],
        'Customer support' => [
            'description' => 'Handles orders, customers, bookings and messages.',
            'permissions' => [
                'orders' => '*', 'customers' => ['view', 'update'], 'service_requests' => ['view', 'update'],
                'messages' => '*', 'reviews' => ['view', 'update'],
            ],
        ],
        'Content editor' => [
            'description' => 'Edits pages, the journal, navigation, store locations and store settings.',
            'permissions' => [
                'pages' => '*', 'posts' => '*', 'faqs' => '*', 'menus' => '*', 'testimonials' => '*', 'stores' => '*',
                'appearance' => '*',
            ],
        ],
    ],
];
