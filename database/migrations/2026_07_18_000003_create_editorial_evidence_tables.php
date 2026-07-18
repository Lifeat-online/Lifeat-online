<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_item_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('content_type')->nullable();
            $table->longText('content');
            $table->string('content_hash', 64)->index();
            $table->json('response_headers')->nullable();
            $table->text('fetch_error')->nullable();
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
        });

        Schema::create('story_clusters', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('fingerprint', 64)->unique();
            $table->string('status', 30)->default('open')->index();
            $table->timestamps();
        });

        Schema::create('research_item_story_cluster', function (Blueprint $table) {
            $table->foreignId('research_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_cluster_id')->constrained()->cascadeOnDelete();
            $table->primary(['research_item_id', 'story_cluster_id']);
        });

        Schema::create('editorial_dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_cluster_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('editorial_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_dossier_id')->constrained()->cascadeOnDelete();
            $table->text('claim');
            $table->string('importance', 20)->default('medium')->index();
            $table->string('status', 30)->default('unverified')->index();
            $table->timestamps();
        });

        Schema::create('claim_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('stance', 20)->default('supports')->index();
            $table->text('excerpt')->nullable();
            $table->unsignedTinyInteger('authority_score')->default(50);
            $table->timestamps();
            $table->unique(['editorial_claim_id', 'source_snapshot_id', 'stance'], 'claim_evidence_unique');
        });

        Schema::table('article_briefs', function (Blueprint $table) {
            $table->foreignId('editorial_dossier_id')->nullable()->after('research_item_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('article_briefs', fn (Blueprint $table) => $table->dropConstrainedForeignId('editorial_dossier_id'));
        Schema::dropIfExists('claim_evidence');
        Schema::dropIfExists('editorial_claims');
        Schema::dropIfExists('editorial_dossiers');
        Schema::dropIfExists('research_item_story_cluster');
        Schema::dropIfExists('story_clusters');
        Schema::dropIfExists('source_snapshots');
    }
};
