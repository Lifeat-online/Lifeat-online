<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->default('email')->index();
            $table->string('notification_type')->index();
            $table->nullableMorphs('notifiable');
            $table->string('recipient')->nullable();
            $table->string('status')->default('sent')->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
