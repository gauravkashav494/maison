<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sales person: a storefront account the store sets up in the admin (Customers → Account type).
 * They sign in on the store like any customer, but their orders need no delivery address: they
 * are placed for themselves or for a customer they are serving, against the store's own address
 * (see SalesOrder).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'is_salesperson')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('is_salesperson')->default(false)->after('is_active')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'is_salesperson')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex(['is_salesperson']);
                $table->dropColumn('is_salesperson');
            });
        }
    }
};
