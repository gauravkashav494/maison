<?php

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
use App\Models\Testimonial;

/*
|--------------------------------------------------------------------------
| Client modules and permissions
|--------------------------------------------------------------------------
| The single list of what a client can be given. A SuperAdmin switches modules on per client;
| a Client Admin builds roles from their actions, named "<module>.<action>" (orders.view).
|
| A module or action missing from this list is refused, so forgetting to add a new screen here
| fails closed. ClientPermissionsTest checks that every resource on the client panel has an entry.
*/

return [
    'modules' => [
        'orders' => ['label' => 'Orders', 'group' => 'Sales', 'model' => Order::class, 'actions' => ['view', 'update']],
        'customers' => ['label' => 'Customers', 'group' => 'Sales', 'model' => Customer::class],

        'products' => ['label' => 'Products', 'group' => 'Catalogue', 'model' => Product::class],
        'categories' => ['label' => 'Categories', 'group' => 'Catalogue', 'model' => Category::class],
        'collections' => ['label' => 'Collections', 'group' => 'Catalogue', 'model' => Collection::class],
        'reviews' => ['label' => 'Reviews', 'group' => 'Catalogue', 'model' => Review::class, 'actions' => ['view', 'update', 'delete']],

        'services' => ['label' => 'Services', 'group' => 'Services', 'model' => Service::class],
        'service_areas' => ['label' => 'Service areas', 'group' => 'Services', 'model' => ServiceArea::class],
        'service_requests' => ['label' => 'Bookings & quotes', 'group' => 'Services', 'model' => ServiceRequest::class],
        'projects' => ['label' => 'Projects', 'group' => 'Services', 'model' => Project::class],
        'testimonials' => ['label' => 'Testimonials', 'group' => 'Services', 'model' => Testimonial::class],

        'pages' => ['label' => 'Pages', 'group' => 'Content', 'model' => Page::class],
        'posts' => ['label' => 'Journal', 'group' => 'Content', 'model' => Post::class],
        'faqs' => ['label' => 'FAQs', 'group' => 'Content', 'model' => Faq::class],
        'menus' => ['label' => 'Navigation', 'group' => 'Content', 'model' => Menu::class],
        'stores' => ['label' => 'Store locations', 'group' => 'Content', 'model' => Store::class],

        'coupons' => ['label' => 'Promo codes', 'group' => 'Marketing', 'model' => Coupon::class],
        'subscribers' => ['label' => 'Subscribers', 'group' => 'Marketing', 'model' => Subscriber::class],
        'messages' => ['label' => 'Messages', 'group' => 'Marketing', 'model' => ContactMessage::class, 'actions' => ['view', 'update', 'delete']],

        'appearance' => ['label' => 'Store settings', 'group' => 'Appearance', 'model' => null, 'actions' => ['update']],
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
                'products' => '*', 'categories' => '*', 'collections' => '*', 'reviews' => '*',
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
