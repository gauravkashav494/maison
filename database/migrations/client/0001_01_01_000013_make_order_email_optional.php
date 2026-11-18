<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email is optional at checkout (the phone number is the required contact), in a client database. The shared-database copy is
 * database/migrations/2026_11_17_140000_make_order_email_optional.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'email')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('email')->nullable()->change());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'email')) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('email')->nullable(false)->change());
        }
    }
};
