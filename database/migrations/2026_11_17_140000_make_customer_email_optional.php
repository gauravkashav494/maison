<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer added in the admin needs a phone number; the email is optional. Several customers
 * of one client may have no email (the client_id + email unique key allows any number of NULLs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversed: customers saved without an email would make NOT NULL fail.
    }
};
