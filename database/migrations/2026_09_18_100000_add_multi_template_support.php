<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-template storefront support. Purely additive: nullable columns, no renames,
 * no deletions. A null `template` means "visible in every template".
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'categories', 'collections', 'menus'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('template', 40)->nullable()->index();
            });
        }

        // Grocery-style product attributes (ignored by templates that do not use them).
        Schema::table('products', function (Blueprint $t) {
            $t->boolean('is_veg')->nullable();           // null = not applicable
            $t->string('shelf_life', 80)->nullable();
            $t->string('country_of_origin', 80)->nullable();
            $t->text('ingredients')->nullable();
            $t->text('storage_instructions')->nullable();
            $t->unsignedSmallInteger('max_qty')->nullable(); // per-order cap, e.g. 5 for milk
        });

        // The existing catalogue and menus were built for the Fashion template.
        DB::table('products')->whereNull('template')->update(['template' => 'fashion']);
        DB::table('categories')->whereNull('template')->update(['template' => 'fashion']);
        DB::table('collections')->whereNull('template')->update(['template' => 'fashion']);
        DB::table('menus')->whereNull('template')->update(['template' => 'fashion']);

        if (! DB::table('settings')->where('key', 'appearance')->exists()) {
            Setting::set('appearance', ['active_template' => 'fashion']);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn(['template', 'is_veg', 'shelf_life', 'country_of_origin', 'ingredients', 'storage_instructions', 'max_qty']);
        });
        foreach (['categories', 'collections', 'menus'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('template'));
        }
        DB::table('settings')->where('key', 'appearance')->delete();
    }
};
