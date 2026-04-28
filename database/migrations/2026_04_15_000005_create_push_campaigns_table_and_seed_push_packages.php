<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->text('message');
            $table->timestamp('schedule_at')->nullable();
            $table->string('audience_scope')->default('listing_city');
            $table->string('target_city')->nullable();
            $table->string('target_region')->nullable();
            $table->unsignedInteger('radius_km')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('package_expires_at')->nullable();
            $table->unsignedBigInteger('active_subscription_id')->nullable();
            $table->timestamps();
        });

        $now = now();
        $pushTypeId = DB::table('package_types')->where('slug', 'push_campaign')->value('id');

        if ($pushTypeId) {
            DB::table('packages')->updateOrInsert(
                ['slug' => 'push-campaign-once'],
                [
                    'package_type_id' => $pushTypeId,
                    'name' => 'Push Campaign Once-Off',
                    'description' => 'One scheduled push campaign for an active business listing.',
                    'billing_model' => 'once_off',
                    'is_self_service' => true,
                    'duration_days' => 7,
                    'status' => 'active',
                    'settings_json' => json_encode(['entitlement_code' => 'push_notification']),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            DB::table('packages')->updateOrInsert(
                ['slug' => 'push-campaign-monthly'],
                [
                    'package_type_id' => $pushTypeId,
                    'name' => 'Push Campaign Monthly',
                    'description' => 'Monthly push campaign package for active businesses.',
                    'billing_model' => 'monthly',
                    'is_self_service' => true,
                    'duration_days' => 30,
                    'status' => 'active',
                    'settings_json' => json_encode(['entitlement_code' => 'push_notification']),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $onceId = DB::table('packages')->where('slug', 'push-campaign-once')->value('id');
            $monthlyId = DB::table('packages')->where('slug', 'push-campaign-monthly')->value('id');

            DB::table('package_prices')->updateOrInsert(
                ['package_id' => $onceId],
                [
                    'currency' => 'ZAR',
                    'amount' => 250.00,
                    'vat_inclusive' => true,
                    'effective_from' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            DB::table('package_prices')->updateOrInsert(
                ['package_id' => $monthlyId],
                [
                    'currency' => 'ZAR',
                    'amount' => 95.00,
                    'vat_inclusive' => true,
                    'effective_from' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('package_prices')->whereIn('package_id', function ($query) {
            $query->select('id')->from('packages')->whereIn('slug', ['push-campaign-once', 'push-campaign-monthly']);
        })->delete();

        DB::table('packages')->whereIn('slug', ['push-campaign-once', 'push-campaign-monthly'])->delete();

        Schema::dropIfExists('push_campaigns');
    }
};
