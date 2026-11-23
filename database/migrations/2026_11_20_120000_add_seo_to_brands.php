<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each brand has its own storefront page (/brands/{slug}), so it gets the same search & social
 * columns as categories and products (App\Models\Concerns\HasSeo), edited in SEO → Search
 * listings. The client-database copy is database/migrations/client/0001_01_01_000020_add_seo_to_brands.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brands') || Schema::hasColumn('brands', 'meta_title')) {
            return;
        }

        Schema::table('brands', function (Blueprint $table) {
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('brands', 'meta_title')) {
            Schema::table('brands', fn (Blueprint $table) => $table->dropColumn(['meta_title', 'meta_description', 'og_image', 'canonical_url', 'noindex']));
        }
    }
};
