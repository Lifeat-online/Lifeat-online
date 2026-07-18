<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('locale', 12)->default('en');
            $table->string('ip_hash', 64)->nullable()->index();
            $table->timestamp('last_activity_at')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('ai_chat_session_id');
            $table->foreign('ai_chat_session_id')->references('id')->on('ai_chat_sessions')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->json('sources')->nullable();
            $table->foreignId('ai_generation_id')->nullable()->constrained('ai_generations')->nullOnDelete();
            $table->timestamps();
            $table->index(['ai_chat_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
    }
};
