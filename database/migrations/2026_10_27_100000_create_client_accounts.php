<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separates the three kinds of account the platform has.
 *
 *   users          SuperAdmin panel accounts only (/admin).
 *   client_users   Client Admin and Admin Staff accounts (/client), each tied to one client.
 *   customers      Shoppers on a client's storefront, kept in the platform database and
 *                  scoped by client_id.
 *
 * A client is the tenant: it is assigned a store (template) and owns its own database, so two
 * clients running the same store never share rows. Plans carry the default limits a client
 * inherits; per-client columns override them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                // Pricing is not implemented yet: the label is what the admin shows.
                $table->string('price_label')->default('₹X');
                $table->unsignedInteger('product_limit')->nullable();
                $table->unsignedInteger('image_limit')->default(50);
                $table->unsignedInteger('storage_limit_mb')->nullable();
                $table->boolean('is_default')->default(false);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->foreignId('storefront_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
                $table->string('domain')->nullable()->unique();
                // Set only when the database name differs from the generated client_<slug>.
                $table->string('database')->nullable();
                $table->boolean('is_active')->default(true);
                // Null = inherit from the plan.
                $table->unsignedInteger('product_limit')->nullable();
                $table->unsignedInteger('image_limit')->nullable();
                $table->unsignedInteger('storage_limit_mb')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('client_users')) {
            Schema::create('client_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('admin_staff'); // client_admin | admin_staff
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
                $table->unique(['client_id', 'email']);
            });
        }

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('email');
                $table->string('phone')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
                $table->unique(['client_id', 'email']);
            });
        }

        // One reset-token table per broker: a token issued to a customer must not be usable
        // against a staff account that happens to share the address.
        foreach (['customer_password_reset_tokens', 'client_user_password_reset_tokens'] as $table) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) {
                    $t->string('email')->primary();
                    $t->string('token');
                    $t->timestamp('created_at')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_user_password_reset_tokens');
        Schema::dropIfExists('customer_password_reset_tokens');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('client_users');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('plans');
    }
};
