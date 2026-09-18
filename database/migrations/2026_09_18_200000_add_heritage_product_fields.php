<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Richer grocery product data used by the Heritage template (nutrition table,
 * benefits, usage, dietary tags) and long-form category copy. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->json('dietary_tags')->nullable();      // ["Organic", "Vegan", "Gluten-free"]
            $t->json('nutrition')->nullable();         // [{"label": "Energy", "value": "350 kcal"}]
            $t->text('benefits')->nullable();
            $t->text('usage_instructions')->nullable();
        });

        Schema::table('categories', function (Blueprint $t) {
            $t->text('content')->nullable();           // long SEO copy shown at the bottom of category pages
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['dietary_tags', 'nutrition', 'benefits', 'usage_instructions']));
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn('content'));
    }
};
