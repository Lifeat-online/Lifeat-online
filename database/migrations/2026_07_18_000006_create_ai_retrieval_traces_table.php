<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_retrieval_traces', function (Blueprint $table) {
            $table->id();
            $table->uuid('trace_id')->unique();
            $table->string('query_hash', 64)->index();
            $table->string('locale', 12);
            $table->unsignedInteger('lexical_candidates')->default(0);
            $table->unsignedInteger('semantic_candidates')->default(0);
            $table->json('selected_sources');
            $table->string('embedding_model')->nullable();
            $table->unsignedSmallInteger('embedding_dimensions')->nullable();
            $table->unsignedInteger('latency_ms');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_retrieval_traces');
    }
};
