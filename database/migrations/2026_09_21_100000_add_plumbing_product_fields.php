<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technical product data used by the Plumbing template: a specification table,
 * typical applications and installation notes. Additive only; other templates
 * ignore these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->json('specifications')->nullable();    // [{"label": "Material", "value": "CPVC"}]
            $t->text('applications')->nullable();      // one application per line
            $t->text('installation_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['specifications', 'applications', 'installation_notes']));
    }
};
