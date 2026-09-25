<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Per-store plan limits set by the super admin. Null = unlimited, so existing stores are unchanged. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefronts', function (Blueprint $table) {
            $table->unsignedInteger('product_limit')->nullable()->after('is_active');
            $table->unsignedInteger('storage_limit_mb')->nullable()->after('product_limit');
        });
    }

    public function down(): void
    {
        Schema::table('storefronts', fn (Blueprint $table) => $table->dropColumn(['product_limit', 'storage_limit_mb']));
    }
};
