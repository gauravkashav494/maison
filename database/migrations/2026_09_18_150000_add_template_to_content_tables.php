<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-template content: pages, FAQs and journal posts can belong to one storefront
 * template (null = shared by all). Slugs become unique per template so each template
 * can have its own /about, /faq, /privacy… at the same URLs. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        // pages.template already holds the page *layout* (about, legal, faq…), so the
        // owning storefront template lives in its own column.
        Schema::table('pages', function (Blueprint $t) {
            $t->string('storefront_template', 40)->nullable()->index();
            $t->dropUnique(['slug']);
            $t->unique(['slug', 'storefront_template']);
        });

        Schema::table('posts', function (Blueprint $t) {
            $t->string('template', 40)->nullable()->index();
            $t->dropUnique(['slug']);
            $t->unique(['slug', 'template']);
        });

        Schema::table('faqs', function (Blueprint $t) {
            $t->string('template', 40)->nullable()->index();
        });

        // Existing content was written for the Fashion template.
        DB::table('pages')->whereNull('storefront_template')->update(['storefront_template' => 'fashion']);
        DB::table('posts')->whereNull('template')->update(['template' => 'fashion']);
        DB::table('faqs')->whereNull('template')->update(['template' => 'fashion']);
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $t) {
            $t->dropUnique(['slug', 'storefront_template']);
            $t->dropColumn('storefront_template');
            $t->unique('slug');
        });
        Schema::table('posts', function (Blueprint $t) {
            $t->dropUnique(['slug', 'template']);
            $t->dropColumn('template');
            $t->unique('slug');
        });
        Schema::table('faqs', fn (Blueprint $t) => $t->dropColumn('template'));
    }
};
