<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operator_conversation_id');
            $table->foreign('operator_conversation_id')->references('id')->on('operator_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('goal');
            $table->string('status', 30)->index();
            $table->json('plan')->nullable();
            $table->json('sources')->nullable();
            $table->json('usage')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('step_limit')->default(12);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('operator_task_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('operator_task_id');
            $table->foreign('operator_task_id')->references('id')->on('operator_tasks')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('action', 30);
            $table->string('tool', 100)->nullable()->index();
            $table->string('risk', 4)->nullable();
            $table->string('status', 30)->index();
            $table->json('arguments')->nullable();
            $table->json('result')->nullable();
            $table->foreignUuid('operator_tool_run_id')->nullable()->constrained('operator_tool_runs')->nullOnDelete();
            $table->foreignUuid('operator_tool_approval_id')->nullable()->constrained('operator_tool_approvals')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['operator_task_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_task_steps');
        Schema::dropIfExists('operator_tasks');
    }
};
