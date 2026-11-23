<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The rating and review count an admin types on a product (reviews imported from elsewhere)
 * get their own columns, so storefront reviews add to them instead of replacing them.
 * products.rating / products.review_count stay as the combined figures the storefront shows.
 * The client-database copy is database/migrations/client/0001_01_01_000009_add_review_baseline_to_products.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'base_review_count')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('base_rating', 2, 1)->default(0)->after('review_count');
            $table->unsignedInteger('base_review_count')->default(0)->after('base_rating');
        });

        // Whatever the shown figures hold beyond the approved reviews was typed in: keep it as the baseline.
        $hasReviews = Schema::hasTable('reviews');
        DB::table('products')->where('review_count', '>', 0)->orderBy('id')->select('id', 'rating', 'review_count')
            ->each(function ($p) use ($hasReviews) {
                $real = $hasReviews
                    ? DB::table('reviews')->where('product_id', $p->id)->where('is_approved', true)->whereNull('deleted_at')
                        ->selectRaw('count(*) as n, coalesce(sum(rating), 0) as total')->first()
                    : (object) ['n' => 0, 'total' => 0];
                $count = max(0, (int) $p->review_count - (int) $real->n);
                $rating = $count > 0 ? max(0, min(5, ((float) $p->rating * (int) $p->review_count - (float) $real->total) / $count)) : 0;
                DB::table('products')->where('id', $p->id)->update(['base_review_count' => $count, 'base_rating' => round($rating, 1)]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'base_review_count')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['base_rating', 'base_review_count']));
        }
    }
};
