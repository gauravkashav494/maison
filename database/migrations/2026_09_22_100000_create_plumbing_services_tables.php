<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service-business content for the Plumbing Services template: services, service
 * areas, testimonials, before/after projects and booking / quote requests. Purely
 * additive — the ecommerce tables are untouched; contact messages gain two optional
 * columns used by the services contact form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('template', 40)->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->string('icon', 40)->nullable();
            $table->string('image')->nullable();
            $table->string('excerpt', 300)->nullable();
            $table->longText('description')->nullable();
            $table->json('problems')->nullable();      // common problems this service fixes
            $table->json('included')->nullable();      // what's included
            $table->json('faqs')->nullable();          // service-specific Q&A
            $table->string('price_note', 120)->nullable(); // e.g. "Visit charge ₹199, adjusted in final bill" — optional, never invented
            $table->boolean('is_emergency')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();
            $table->unique(['template', 'slug']);
        });

        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('template', 40)->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->string('state', 80)->nullable();
            $table->string('image')->nullable();
            $table->string('excerpt', 300)->nullable();
            $table->longText('description')->nullable();
            $table->json('localities')->nullable();    // neighbourhoods covered
            $table->string('response_time', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->string('og_image')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();
            $table->unique(['template', 'slug']);
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('template', 40)->nullable()->index();
            $table->string('name', 80);
            $table->text('body');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location', 80)->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('template', 40)->nullable()->index();
            $table->string('title');
            $table->string('slug');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('before_image')->nullable();
            $table->string('after_image')->nullable();
            $table->text('description')->nullable();
            $table->string('location', 80)->nullable();
            $table->date('completed_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['template', 'slug']);
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->string('type', 20)->default('booking'); // booking | emergency | quote
            $table->string('template', 40)->nullable()->index();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name', 120)->nullable();
            $table->text('problem')->nullable();
            $table->string('address', 300)->nullable();
            $table->string('area', 120)->nullable();
            $table->foreignId('service_area_id')->nullable()->constrained()->nullOnDelete();
            $table->date('preferred_date')->nullable();
            $table->string('time_slot', 20)->nullable(); // morning | afternoon | evening | asap
            $table->string('name', 80);
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('new'); // new | contacted | scheduled | completed | cancelled
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->index(['phone', 'reference']);
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->string('service', 120)->nullable()->after('subject');
            $table->string('location', 120)->nullable()->after('service');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', fn (Blueprint $t) => $t->dropColumn(['service', 'location']));
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('service_areas');
        Schema::dropIfExists('services');
    }
};
