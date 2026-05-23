<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->index();
            $table->text('url')->nullable();
            $table->text('query')->nullable();
            $table->string('locale', 20)->default('en-ZA');
            $table->string('country', 10)->default('ZA');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('fetch_interval_minutes')->default(60);
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('research_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_source_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_name')->nullable();
            $table->string('source_type')->nullable()->index();
            $table->text('source_url')->nullable();
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('author')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->json('detected_locations')->nullable();
            $table->json('detected_entities')->nullable();
            $table->string('fingerprint')->unique();
            $table->string('status')->default('new')->index();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('research_items')->nullOnDelete();
            $table->timestamps();

            $table->index(['research_source_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_items');
        Schema::dropIfExists('research_sources');
    }
};
