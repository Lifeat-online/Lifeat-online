<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_manager_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_key')->index();
            $table->string('domain', 60)->index();
            $table->string('action_type', 80)->index();
            $table->string('title');
            $table->text('summary');
            $table->text('rationale')->nullable();
            $table->string('status', 40)->default('proposed')->index();
            $table->string('risk_level', 40)->default('medium')->index();
            $table->string('required_mode', 40)->default('approval')->index();
            $table->decimal('impact_score', 5, 2)->default(0);
            $table->decimal('confidence_score', 5, 2)->default(0);
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('expected_value', 12, 2)->default(0);
            $table->nullableMorphs('source');
            $table->json('payload')->nullable();
            $table->string('proposed_by', 120)->default('autonomous_ai_manager');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_manager_actions');
    }
};
