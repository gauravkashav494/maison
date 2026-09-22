<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-store roles: a storefront row per template, a role + storefront on users, and a
 * template owner column on the records that were not scoped yet (orders, reviews, contact
 * messages, subscribers, coupons). Purely additive; existing rows are backfilled, never removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefronts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 60)->unique();
            $table->string('template', 40)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 40)->nullable()->index()->after('is_admin');
            $table->foreignId('storefront_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('storefront_id');
        });

        // Existing staff accounts keep full access.
        DB::table('users')->where('is_admin', true)->update(['role' => 'super_admin']);

        foreach (['orders', 'reviews', 'contact_messages', 'subscribers', 'coupons'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('template', 40)->nullable()->index();
            });
        }

        // Backfill: an order belongs to the template of the products it contains, a review to its product's template.
        foreach (DB::table('order_items')->join('products', 'products.id', '=', 'order_items.product_id')
            ->select('order_items.order_id', 'products.template')->whereNotNull('products.template')->orderBy('order_items.id')->get()
            ->unique('order_id') as $row) {
            DB::table('orders')->where('id', $row->order_id)->whereNull('template')->update(['template' => $row->template]);
        }
        foreach (DB::table('reviews')->join('products', 'products.id', '=', 'reviews.product_id')
            ->select('reviews.id', 'products.template')->whereNotNull('products.template')->get() as $row) {
            DB::table('reviews')->where('id', $row->id)->update(['template' => $row->template]);
        }
        // Contact messages that came through the services form carry a service name.
        DB::table('contact_messages')->whereNotNull('service')->where('service', '!=', '')->update(['template' => 'plumbing-services']);
    }

    public function down(): void
    {
        foreach (['orders', 'reviews', 'contact_messages', 'subscribers', 'coupons'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn('template'));
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storefront_id');
            $table->dropColumn(['role', 'is_active']);
        });
        Schema::dropIfExists('storefronts');
    }
};
