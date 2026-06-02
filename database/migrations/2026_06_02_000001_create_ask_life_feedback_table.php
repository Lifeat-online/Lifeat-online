<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ask_life_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_generation_id')->nullable()->constrained('ai_generations')->nullOnDelete();
            $table->string('rating', 24)->index();
            $table->string('intent', 80)->nullable()->index();
            $table->string('source', 40)->nullable()->index();
            $table->text('question');
            $table->text('answer');
            $table->json('source_ids')->nullable();
            $table->json('sources')->nullable();
            $table->json('page_context')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ask_life_feedback');
    }
};
