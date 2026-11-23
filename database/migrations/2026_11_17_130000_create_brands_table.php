<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Brand master: each store keeps its own list of brands, and a product points at one of them
 * (products.brand_id). products.brand keeps the brand's name, so the shop's Brand filter,
 * search and cards read it as before (App\Models\Product keeps it in step).
 *
 * The brand names products already carry become brands, per client and store. The
 * client-database copy is database/migrations/client/0001_01_01_000012_create_brands_table.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable()->index();
                $table->string('template', 40)->nullable()->index();
                $table->string('name');
                $table->string('slug');
                $table->string('logo')->nullable();
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['client_id', 'slug']);
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'brand_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('brand_id')->nullable()->index()->after('brand');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'brand_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['brand_id']);
                $table->dropColumn('brand_id');
            });
        }
        Schema::dropIfExists('brands');
    }

    /** One brand per distinct name a client's products use; the products are linked to it. */
    private function backfill(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'brand')) {
            return;
        }

        $rows = DB::table('products')->whereNull('brand_id')->whereNotNull('brand')->where('brand', '!=', '')
            ->select('id', 'client_id', 'template', 'brand')->orderBy('id')->get();

        $ids = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->brand);
            $slug = Str::slug($name);
            if ($slug === '') {
                continue;
            }
            $key = ($row->client_id ?? 'none').'|'.$slug;

            $ids[$key] ??= DB::table('brands')->where('client_id', $row->client_id)->where('slug', $slug)->value('id')
                ?? DB::table('brands')->insertGetId([
                    'client_id' => $row->client_id,
                    'template' => $row->template,
                    'name' => $name,
                    'slug' => $slug,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $name === $row->brand
                ? DB::table('products')->where('id', $row->id)->update(['brand_id' => $ids[$key]])
                : DB::table('products')->where('id', $row->id)->update(['brand_id' => $ids[$key], 'brand' => $name]);
        }
    }
};
