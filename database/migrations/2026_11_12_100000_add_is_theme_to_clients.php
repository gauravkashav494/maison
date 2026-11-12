<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Theme previews: one internal client per template (theme-<template>), holding that template's
 * sample content in its own database. It is public at theme-<template>.<base domain> and hidden
 * from every client list, count and total.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_theme')->default(false)->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['is_theme']);
            $table->dropColumn('is_theme');
        });
    }
};
