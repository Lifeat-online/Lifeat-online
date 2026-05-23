<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->string('feature_key')->index();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->string('input_hash', 64)->nullable()->index();
            $table->text('input_summary')->nullable();
            $table->string('output_language')->nullable();
            $table->json('output_payload')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('token_input_estimate')->nullable();
            $table->unsignedInteger('token_output_estimate')->nullable();
            $table->decimal('cost_estimate', 12, 6)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        if (! Schema::hasColumn('articles', 'seo_title')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->string('seo_title')->nullable()->after('excerpt');
            });
        }

        if (! Schema::hasColumn('articles', 'seo_description')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->text('seo_description')->nullable()->after('seo_title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('articles', 'seo_description')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropColumn('seo_description');
            });
        }

        if (Schema::hasColumn('articles', 'seo_title')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropColumn('seo_title');
            });
        }

        Schema::dropIfExists('ai_generations');
    }
};
