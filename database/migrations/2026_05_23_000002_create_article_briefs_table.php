<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_generation_id')->nullable()->constrained('ai_generations')->nullOnDelete();
            $table->foreignId('suggested_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('title');
            $table->longText('angle')->nullable();
            $table->json('source_urls')->nullable();
            $table->json('suggested_tags')->nullable();
            $table->decimal('locality_score', 5, 2)->default(0);
            $table->decimal('newsworthiness_score', 5, 2)->default(0);
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->decimal('duplicate_risk', 5, 2)->default(0);
            $table->text('editorial_notes')->nullable();
            $table->string('status')->default('pending_review')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('research_item_id');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_briefs');
    }
};
