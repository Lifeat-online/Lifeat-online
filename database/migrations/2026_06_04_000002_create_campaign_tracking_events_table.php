<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->string('trackable_type');
            $table->unsignedBigInteger('trackable_id');
            $table->string('event_type', 32);
            $table->string('tracking_token', 80)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->text('referrer')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['trackable_type', 'trackable_id'], 'campaign_tracking_trackable_idx');
            $table->index(['trackable_type', 'trackable_id', 'event_type', 'occurred_at'], 'campaign_tracking_type_date_idx');
            $table->unique(['trackable_type', 'trackable_id', 'event_type', 'tracking_token'], 'campaign_tracking_token_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_tracking_events');
    }
};
