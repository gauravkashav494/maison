<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shopper reset tokens are keyed on client + email (see ClientScopedTokenRepository): the same
 * address may be a shopper of two stores, and a link from one must not reset the other.
 *
 * The table only holds short-lived tokens, so it is rebuilt rather than altered; any link still
 * pending simply has to be requested again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id');
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            $table->primary(['client_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
