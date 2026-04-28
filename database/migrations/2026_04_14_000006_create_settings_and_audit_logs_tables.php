<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general')->index();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });

        $now = now();
        DB::table('settings')->insert([
            ['key' => 'writer.per_word_rate', 'value' => '0.00', 'type' => 'decimal', 'group' => 'writers', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pricing.business_directory_6m', 'value' => '500.00', 'type' => 'decimal', 'group' => 'pricing', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pricing.business_directory_self_service_6m', 'value' => '750.00', 'type' => 'decimal', 'group' => 'pricing', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pricing.business_directory_staff_assisted_6m', 'value' => '500.00', 'type' => 'decimal', 'group' => 'pricing', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pricing.event_one_off', 'value' => '0.00', 'type' => 'decimal', 'group' => 'pricing', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pricing.event_monthly', 'value' => '0.00', 'type' => 'decimal', 'group' => 'pricing', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pricing.push_notification', 'value' => '0.00', 'type' => 'decimal', 'group' => 'pricing', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'geo.default_radius_km', 'value' => '25', 'type' => 'integer', 'group' => 'geo', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'geo.fallback_radius_km', 'value' => '100', 'type' => 'integer', 'group' => 'geo', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'billing.vat_percentage', 'value' => '15', 'type' => 'decimal', 'group' => 'billing', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'billing.invoice_prefix', 'value' => 'LIFE', 'type' => 'string', 'group' => 'billing', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
    }
};
