<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_tool_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->string('tool', 100)->index();
            $table->string('risk', 4);
            $table->string('arguments_hash', 64);
            $table->string('record_version', 100);
            $table->string('signature', 64);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('operator_tool_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 100)->index();
            $table->string('risk', 4)->index();
            $table->json('arguments');
            $table->json('result')->nullable();
            $table->string('status', 30)->index();
            $table->string('idempotency_key', 64)->unique();
            $table->uuid('operator_tool_approval_id')->nullable();
            $table->foreign('operator_tool_approval_id')->references('id')->on('operator_tool_approvals')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_tool_runs');
        Schema::dropIfExists('operator_tool_approvals');
    }
};
