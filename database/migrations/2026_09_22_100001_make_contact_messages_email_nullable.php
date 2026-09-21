<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phone-first contact forms (Plumbing Services) may omit the email address. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', fn (Blueprint $t) => $t->string('email')->nullable()->change());
    }

    public function down(): void
    {
        // Left nullable on rollback: tightening it could fail on rows without an email.
    }
};
