<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        }

        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 80);
            $table->string('source_id', 120);
            $table->string('locale', 12)->default('en');
            $table->string('title');
            $table->string('canonical_url')->nullable();
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->string('content_hash', 64);
            $table->unsignedInteger('index_version')->default(1);
            $table->string('visibility', 24)->default('private')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'locale'], 'knowledge_documents_source_unique');
        });

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->longText('content');
            $table->string('content_hash', 64)->index();
            $table->unsignedInteger('token_count')->default(0);
            $table->unsignedInteger('character_count')->default(0);
            $table->json('embedding')->nullable();
            $table->string('embedding_provider', 80)->nullable();
            $table->string('embedding_model', 120)->nullable();
            $table->unsignedSmallInteger('embedding_dimensions')->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->timestamps();
            $table->unique(['knowledge_document_id', 'position']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $dimensions = (int) config('ai_platform.embeddings.dimensions', 1536);
            DB::statement("ALTER TABLE knowledge_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(content, ''))) STORED");
            DB::statement("ALTER TABLE knowledge_chunks ADD COLUMN embedding_vector vector({$dimensions})");
            DB::statement('CREATE INDEX knowledge_chunks_search_vector_idx ON knowledge_chunks USING GIN (search_vector)');
            DB::statement('CREATE INDEX knowledge_chunks_embedding_vector_idx ON knowledge_chunks USING hnsw (embedding_vector vector_cosine_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_documents');
    }
};
