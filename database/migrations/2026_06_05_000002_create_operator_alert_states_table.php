<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_alert_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint', 64)->index();
            $table->string('target', 64)->index();
            $table->string('severity', 16)->index();
            $table->unsignedTinyInteger('retries_sent')->default(0);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->json('last_payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
            $table->index(['target', 'severity', 'acknowledged_at'], 'oas_target_severity_ack_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_alert_states');
    }
};
