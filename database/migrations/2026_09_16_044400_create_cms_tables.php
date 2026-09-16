<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Shared SEO columns added to every public-facing content type. */
    private function seo(Blueprint $table): void
    {
        $table->string('meta_title')->nullable();
        $table->string('meta_description', 320)->nullable();
        $table->string('og_image')->nullable();
        $table->string('canonical_url')->nullable();
        $table->boolean('noindex')->default(false);
    }

    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_menu')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $this->seo($table);
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('season')->nullable();
            $table->text('description')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->string('hero_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $this->seo($table);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->text('description')->nullable();
            $table->longText('details')->nullable();
            $table->text('materials')->nullable();
            $table->text('care')->nullable();
            $table->unsignedInteger('price');            // stored in whole rupees
            $table->unsignedInteger('compare_at_price')->nullable();
            $table->json('images')->nullable();           // ordered list of paths/URLs
            $table->json('colors')->nullable();           // [{name, hex}]
            $table->json('sizes')->nullable();            // ["S","M",...]
            $table->unsignedInteger('stock')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_new')->default(false);
            $table->boolean('is_best_seller')->default(false);
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $this->seo($table);
            $table->timestamps();
        });

        Schema::create('collection_product', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['collection_id', 'product_id']);
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->unique(); // header, footer_shop, footer_collections, footer_about, footer_service, legal
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('group')->nullable();          // mega-menu column heading
            $table->string('image')->nullable();          // mega-menu tile image
            $table->string('eyebrow')->nullable();        // mega-menu tile eyebrow
            $table->string('badge')->nullable();          // e.g. product count shown beside label
            $table->boolean('is_accent')->default(false); // e.g. "Sale" in rouge
            $table->boolean('opens_in_new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('template')->default('default'); // default | legal
            $table->string('eyebrow')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $this->seo($table);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('image')->nullable();
            $table->string('read_time')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $this->seo($table);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['subscribers', 'settings', 'posts', 'pages', 'menu_items', 'menus', 'collection_product', 'products', 'collections', 'categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
