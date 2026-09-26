<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The schema of a single store.
 *
 * Every store database is built from this file — catalogue, content, navigation, orders and
 * service tables with the relationships between them. Platform tables (users, stores, settings,
 * sessions) are deliberately absent: they live once in the main database and are shared by all
 * stores, which is why nothing here points at them with a foreign key.
 *
 * It runs in two places:
 *   • `php artisan stores:provision`  → into each store's own database
 *   • `php artisan migrate`           → into the main database, where it is a no-op unless the
 *                                       store tables are missing (shared mode keeps them there)
 *
 * Later changes to store tables belong in this directory too, so every store picks them up.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id('id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('name');
                $table->string('slug');
                $table->string('tagline')->nullable();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('show_in_menu')->default(true);
                $table->integer('sort_order')->default(0);
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 320)->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical_url')->nullable();
                $table->boolean('noindex')->default(false);
                $table->string('template', 40)->nullable();
                $table->text('content')->nullable();
                $table->timestamps();
                $table->unique(['slug'], 'categories_slug_unique');
                $table->index(['template'], 'categories_template_index');
                $table->foreign('parent_id', 'categories_parent_id_foreign')->references('id')->on('categories')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->id('id');
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('name');
                $table->string('slug');
                $table->string('sku')->nullable();
                $table->text('description')->nullable();
                $table->text('details')->nullable();
                $table->text('materials')->nullable();
                $table->text('care')->nullable();
                $table->integer('price');
                $table->integer('compare_at_price')->nullable();
                $table->json('images')->nullable();
                $table->json('colors')->nullable();
                $table->json('sizes')->nullable();
                $table->integer('stock')->default(100);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_new')->default(false);
                $table->boolean('is_best_seller')->default(false);
                $table->decimal('rating', 2, 1)->default(0);
                $table->integer('review_count')->default(0);
                $table->integer('sort_order')->default(0);
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 320)->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical_url')->nullable();
                $table->boolean('noindex')->default(false);
                $table->string('brand')->nullable();
                $table->string('material')->nullable();
                $table->string('video_url')->nullable();
                $table->string('template', 40)->nullable();
                $table->boolean('is_veg')->nullable();
                $table->string('shelf_life', 80)->nullable();
                $table->string('country_of_origin', 80)->nullable();
                $table->text('ingredients')->nullable();
                $table->text('storage_instructions')->nullable();
                $table->smallInteger('max_qty')->nullable();
                $table->json('dietary_tags')->nullable();
                $table->json('nutrition')->nullable();
                $table->text('benefits')->nullable();
                $table->text('usage_instructions')->nullable();
                $table->json('specifications')->nullable();
                $table->text('applications')->nullable();
                $table->text('installation_notes')->nullable();
                $table->timestamps();
                $table->unique(['sku'], 'products_sku_unique');
                $table->unique(['slug'], 'products_slug_unique');
                $table->index(['template'], 'products_template_index');
                $table->foreign('category_id', 'products_category_id_foreign')->references('id')->on('categories')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table) {
                $table->id('id');
                $table->string('name');
                $table->string('slug');
                $table->string('season')->nullable();
                $table->text('description')->nullable();
                $table->text('body')->nullable();
                $table->string('image')->nullable();
                $table->string('hero_image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->integer('sort_order')->default(0);
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 320)->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical_url')->nullable();
                $table->boolean('noindex')->default(false);
                $table->string('template', 40)->nullable();
                $table->timestamps();
                $table->unique(['slug'], 'collections_slug_unique');
                $table->index(['template'], 'collections_template_index');
            });
        }

        if (! Schema::hasTable('collection_product')) {
            Schema::create('collection_product', function (Blueprint $table) {
                $table->unsignedBigInteger('collection_id');
                $table->unsignedBigInteger('product_id');
                $table->integer('sort_order')->default(0);
                $table->foreign('collection_id', 'collection_product_collection_id_foreign')->references('id')->on('collections')->cascadeOnDelete();
                $table->foreign('product_id', 'collection_product_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id('id');
                $table->string('code');
                $table->string('description')->nullable();
                $table->string('type')->default('percent');
                $table->integer('value')->default(0);
                $table->integer('min_subtotal')->default(0);
                $table->integer('usage_limit')->nullable();
                $table->integer('used_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('template', 40)->nullable();
                $table->timestamps();
                $table->unique(['code'], 'coupons_code_unique');
                $table->index(['template'], 'coupons_template_index');
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id('id');
                $table->string('number');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status')->default('confirmed');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->string('shipping_name');
                $table->string('shipping_line1');
                $table->string('shipping_line2')->nullable();
                $table->string('shipping_city');
                $table->string('shipping_state');
                $table->string('shipping_postal_code');
                $table->string('shipping_country')->default('India');
                $table->string('shipping_method')->nullable();
                $table->integer('shipping_cost')->default(0);
                $table->string('payment_method')->nullable();
                $table->string('payment_status')->default('pending');
                $table->integer('subtotal')->default(0);
                $table->integer('discount')->default(0);
                $table->string('coupon_code')->nullable();
                $table->integer('tax')->default(0);
                $table->integer('total')->default(0);
                $table->string('carrier')->nullable();
                $table->string('tracking_number')->nullable();
                $table->date('estimated_delivery')->nullable();
                $table->text('notes')->nullable();
                $table->json('status_history')->nullable();
                $table->string('template', 40)->nullable();
                $table->timestamps();
                $table->unique(['number'], 'orders_number_unique');
                $table->index(['template'], 'orders_template_index');
            });
        }

        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id('id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('name');
                $table->string('sku')->nullable();
                $table->string('image')->nullable();
                $table->string('size')->nullable();
                $table->string('color')->nullable();
                $table->integer('price');
                $table->integer('qty');
                $table->integer('total');
                $table->timestamps();
                $table->foreign('order_id', 'order_items_order_id_foreign')->references('id')->on('orders')->cascadeOnDelete();
                $table->foreign('product_id', 'order_items_product_id_foreign')->references('id')->on('products')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id('id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->smallInteger('rating');
                $table->string('title')->nullable();
                $table->text('body');
                $table->boolean('is_approved')->default(false);
                $table->string('template', 40)->nullable();
                $table->timestamps();
                $table->index(['template'], 'reviews_template_index');
                $table->foreign('product_id', 'reviews_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('pages')) {
            Schema::create('pages', function (Blueprint $table) {
                $table->id('id');
                $table->string('title');
                $table->string('slug');
                $table->string('template')->default('default');
                $table->string('eyebrow')->nullable();
                $table->text('excerpt')->nullable();
                $table->text('body')->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 320)->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical_url')->nullable();
                $table->boolean('noindex')->default(false);
                $table->json('data')->nullable();
                $table->string('storefront_template', 40)->nullable();
                $table->timestamps();
                $table->unique(['slug', 'storefront_template'], 'pages_slug_storefront_template_unique');
                $table->index(['storefront_template'], 'pages_storefront_template_index');
            });
        }

        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table) {
                $table->id('id');
                $table->string('title');
                $table->string('slug');
                $table->string('category')->nullable();
                $table->text('excerpt')->nullable();
                $table->text('body')->nullable();
                $table->string('image')->nullable();
                $table->string('read_time')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 320)->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical_url')->nullable();
                $table->boolean('noindex')->default(false);
                $table->string('template', 40)->nullable();
                $table->timestamps();
                $table->unique(['slug', 'template'], 'posts_slug_template_unique');
                $table->index(['template'], 'posts_template_index');
            });
        }

        if (! Schema::hasTable('faqs')) {
            Schema::create('faqs', function (Blueprint $table) {
                $table->id('id');
                $table->string('category');
                $table->string('question');
                $table->text('answer');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('template', 40)->nullable();
                $table->timestamps();
                $table->index(['template'], 'faqs_template_index');
            });
        }

        if (! Schema::hasTable('menus')) {
            Schema::create('menus', function (Blueprint $table) {
                $table->id('id');
                $table->string('name');
                $table->string('location');
                $table->string('template', 40)->nullable();
                $table->timestamps();
                $table->unique(['location'], 'menus_location_unique');
                $table->index(['template'], 'menus_template_index');
            });
        }

        if (! Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (Blueprint $table) {
                $table->id('id');
                $table->unsignedBigInteger('menu_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('label');
                $table->string('url')->nullable();
                $table->string('group')->nullable();
                $table->string('image')->nullable();
                $table->string('eyebrow')->nullable();
                $table->string('badge')->nullable();
                $table->boolean('is_accent')->default(false);
                $table->boolean('opens_in_new_tab')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->foreign('menu_id', 'menu_items_menu_id_foreign')->references('id')->on('menus')->cascadeOnDelete();
                $table->foreign('parent_id', 'menu_items_parent_id_foreign')->references('id')->on('menu_items')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table) {
                $table->id('id');
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('subject')->nullable();
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->string('template', 40)->nullable();
                $table->string('service', 120)->nullable();
                $table->string('location', 120)->nullable();
                $table->timestamps();
                $table->index(['template'], 'contact_messages_template_index');
            });
        }

        if (! Schema::hasTable('subscribers')) {
            Schema::create('subscribers', function (Blueprint $table) {
                $table->id('id');
                $table->string('email');
                $table->string('source')->nullable();
                $table->string('template', 40)->nullable();
                $table->timestamps();
                $table->unique(['email'], 'subscribers_email_unique');
                $table->index(['template'], 'subscribers_template_index');
            });
        }

        if (! Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id('id');
                $table->string('template', 40)->nullable();
                $table->string('name');
                $table->string('slug');
                $table->string('icon', 40)->nullable();
                $table->string('image')->nullable();
                $table->string('excerpt', 300)->nullable();
                $table->text('description')->nullable();
                $table->json('problems')->nullable();
                $table->json('included')->nullable();
                $table->json('faqs')->nullable();
                $table->string('price_note', 120)->nullable();
                $table->boolean('is_emergency')->default(false);
                $table->boolean('is_popular')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 320)->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical_url')->nullable();
                $table->boolean('noindex')->default(false);
                $table->timestamps();
                $table->index(['template'], 'services_template_index');
                $table->unique(['template', 'slug'], 'services_template_slug_unique');
            });
        }

        if (! Schema::hasTable('service_areas')) {
            Schema::create('service_areas', function (Blueprint $table) {
                $table->id('id');
                $table->string('template', 40)->nullable();
                $table->string('name');
                $table->string('slug');
                $table->string('state', 80)->nullable();
                $table->string('image')->nullable();
                $table->string('excerpt', 300)->nullable();
                $table->text('description')->nullable();
                $table->json('localities')->nullable();
                $table->string('response_time', 60)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->string('meta_title')->nullable();
                $table->string('meta_description', 320)->nullable();
                $table->string('og_image')->nullable();
                $table->string('canonical_url')->nullable();
                $table->boolean('noindex')->default(false);
                $table->timestamps();
                $table->index(['template'], 'service_areas_template_index');
                $table->unique(['template', 'slug'], 'service_areas_template_slug_unique');
            });
        }

        if (! Schema::hasTable('testimonials')) {
            Schema::create('testimonials', function (Blueprint $table) {
                $table->id('id');
                $table->string('template', 40)->nullable();
                $table->string('name', 80);
                $table->text('body');
                $table->smallInteger('rating')->default(5);
                $table->unsignedBigInteger('service_id')->nullable();
                $table->string('location', 80)->nullable();
                $table->string('image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index(['template'], 'testimonials_template_index');
                $table->foreign('service_id', 'testimonials_service_id_foreign')->references('id')->on('services')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id('id');
                $table->string('template', 40)->nullable();
                $table->string('title');
                $table->string('slug');
                $table->unsignedBigInteger('service_id')->nullable();
                $table->string('before_image')->nullable();
                $table->string('after_image')->nullable();
                $table->text('description')->nullable();
                $table->string('location', 80)->nullable();
                $table->date('completed_on')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index(['template'], 'projects_template_index');
                $table->unique(['template', 'slug'], 'projects_template_slug_unique');
                $table->foreign('service_id', 'projects_service_id_foreign')->references('id')->on('services')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('service_requests')) {
            Schema::create('service_requests', function (Blueprint $table) {
                $table->id('id');
                $table->string('reference', 20);
                $table->string('type', 20)->default('booking');
                $table->string('template', 40)->nullable();
                $table->unsignedBigInteger('service_id')->nullable();
                $table->string('service_name', 120)->nullable();
                $table->text('problem')->nullable();
                $table->string('address', 300)->nullable();
                $table->string('area', 120)->nullable();
                $table->unsignedBigInteger('service_area_id')->nullable();
                $table->date('preferred_date')->nullable();
                $table->string('time_slot', 20)->nullable();
                $table->string('name', 80);
                $table->string('phone', 30);
                $table->string('email', 190)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('status', 20)->default('new');
                $table->text('admin_notes')->nullable();
                $table->timestamps();
                $table->index(['phone', 'reference'], 'service_requests_phone_reference_index');
                $table->unique(['reference'], 'service_requests_reference_unique');
                $table->index(['template'], 'service_requests_template_index');
                $table->foreign('service_area_id', 'service_requests_service_area_id_foreign')->references('id')->on('service_areas')->nullOnDelete();
                $table->foreign('service_id', 'service_requests_service_id_foreign')->references('id')->on('services')->nullOnDelete();
            });
        }

    }

    public function down(): void
    {
        foreach ([
            'service_requests',
            'projects',
            'testimonials',
            'service_areas',
            'services',
            'subscribers',
            'contact_messages',
            'menu_items',
            'menus',
            'faqs',
            'posts',
            'pages',
            'reviews',
            'order_items',
            'orders',
            'coupons',
            'collection_product',
            'collections',
            'products',
            'categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
