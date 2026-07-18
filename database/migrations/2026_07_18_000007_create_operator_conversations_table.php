<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('Operator workspace');
            $table->timestamp('last_activity_at')->index();
            $table->timestamps();
        });

        Schema::create('operator_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('operator_conversation_id');
            $table->foreign('operator_conversation_id')->references('id')->on('operator_conversations')->cascadeOnDelete();
            $table->foreignUuid('operator_tool_run_id')->nullable()->constrained('operator_tool_runs')->nullOnDelete();
            $table->string('role', 20);
            $table->string('tool', 100)->nullable();
            $table->text('content');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_messages');
        Schema::dropIfExists('operator_conversations');
    }
};
