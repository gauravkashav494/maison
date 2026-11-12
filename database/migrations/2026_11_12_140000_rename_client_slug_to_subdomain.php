<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client's `slug` is its subdomain (<subdomain>.<base domain>), so the column is named for
 * what it is. The values do not change: databases and media folders keep their names.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clients', 'slug')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('slug', 'subdomain');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->renameIndex('clients_slug_unique', 'clients_subdomain_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clients', 'subdomain')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->renameIndex('clients_subdomain_unique', 'clients_slug_unique');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('subdomain', 'slug');
        });
    }
};
