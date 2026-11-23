<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The search & social columns of a brand, in a client database. The shared-database copy is
 * database/migrations/2026_11_20_120000_add_seo_to_brands.php.
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
