<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('payfast');
            $table->string('status')->default('created')->index();
            $table->json('request_payload_json')->nullable();
            $table->json('response_payload_json')->nullable();
            $table->string('redirect_url')->nullable();
            $table->timestamp('attempted_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('package_expires_at')->nullable()->after('published_at');
            $table->unsignedBigInteger('active_subscription_id')->nullable()->after('package_expires_at');
        });

        $now = now();

        foreach ([
            ['key' => 'pricing.event_one_off', 'value' => '250.00', 'type' => 'decimal', 'group' => 'pricing'],
            ['key' => 'pricing.event_monthly', 'value' => '99.00', 'type' => 'decimal', 'group' => 'pricing'],
            ['key' => 'payfast.merchant_id', 'value' => '10000100', 'type' => 'string', 'group' => 'payfast'],
            ['key' => 'payfast.merchant_key', 'value' => '46f0cd694581a', 'type' => 'string', 'group' => 'payfast'],
            ['key' => 'payfast.passphrase', 'value' => '', 'type' => 'string', 'group' => 'payfast'],
            ['key' => 'payfast.use_sandbox', 'value' => '1', 'type' => 'boolean', 'group' => 'payfast'],
        ] as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['updated_at' => $now, 'created_at' => $now])
            );
        }

        $eventTypeId = DB::table('package_types')->where('slug', 'event_package')->value('id');

        if ($eventTypeId) {
            DB::table('packages')->updateOrInsert(
                ['slug' => 'event-one-off'],
                [
                    'package_type_id' => $eventTypeId,
                    'name' => 'Event One-Off Package',
                    'description' => 'One-off package for publishing a single event tied to an active business listing.',
                    'billing_model' => 'once_off',
                    'is_self_service' => true,
                    'duration_days' => 30,
                    'status' => 'active',
                    'settings_json' => json_encode(['entitlement_code' => 'event_package']),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            DB::table('packages')->updateOrInsert(
                ['slug' => 'event-monthly'],
                [
                    'package_type_id' => $eventTypeId,
                    'name' => 'Event Monthly Package',
                    'description' => 'Recurring monthly event package for an active business listing.',
                    'billing_model' => 'monthly',
                    'is_self_service' => true,
                    'duration_days' => 30,
                    'status' => 'active',
                    'settings_json' => json_encode(['entitlement_code' => 'event_package']),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $oneOffId = DB::table('packages')->where('slug', 'event-one-off')->value('id');
            $monthlyId = DB::table('packages')->where('slug', 'event-monthly')->value('id');

            DB::table('package_prices')->updateOrInsert(
                ['package_id' => $oneOffId],
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
                    'amount' => 99.00,
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
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['package_expires_at', 'active_subscription_id']);
        });

        Schema::dropIfExists('payment_attempts');
    }
};
