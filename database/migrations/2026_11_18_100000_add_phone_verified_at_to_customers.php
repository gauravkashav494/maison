<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the customer proved the phone number is theirs with a WhatsApp code. A customer with a
 * verified phone is a real account even without a password (they sign in with a code).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'phone_verified_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'phone_verified_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('phone_verified_at');
            });
        }
    }
};
