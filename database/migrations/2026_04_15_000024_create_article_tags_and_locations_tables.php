<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['type', 'slug']);
        });

        Schema::create('location_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('city');
            $table->foreignId('parent_id')->nullable()->constrained('location_nodes')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('article_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['article_id', 'tag_id']);
        });

        Schema::create('article_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_node_id')->constrained('location_nodes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['article_id', 'location_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_locations');
        Schema::dropIfExists('article_tag');
        Schema::dropIfExists('location_nodes');
        Schema::dropIfExists('tags');
    }
};
