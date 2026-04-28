<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->text('body')->nullable();
            $table->string('destination_url')->nullable();
            $table->string('creative_image')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('package_expires_at')->nullable();
            $table->unsignedBigInteger('active_subscription_id')->nullable();
            $table->timestamps();
        });

        $now = now();
        $advertTypeId = DB::table('package_types')->where('slug', 'advert_package')->value('id');

        if ($advertTypeId) {
            DB::table('packages')->updateOrInsert(
                ['slug' => 'advert-boost-30d'],
                [
                    'package_type_id' => $advertTypeId,
                    'name' => 'Advert Boost 30 Days',
                    'description' => 'Self-service advert campaign package for a single active business listing.',
                    'billing_model' => 'once_off',
                    'is_self_service' => true,
                    'duration_days' => 30,
                    'status' => 'active',
                    'settings_json' => json_encode(['entitlement_code' => 'advert_package']),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            DB::table('packages')->updateOrInsert(
                ['slug' => 'advert-boost-monthly'],
                [
                    'package_type_id' => $advertTypeId,
                    'name' => 'Advert Boost Monthly',
                    'description' => 'Recurring monthly advert campaign package for an active business listing.',
                    'billing_model' => 'monthly',
                    'is_self_service' => true,
                    'duration_days' => 30,
                    'status' => 'active',
                    'settings_json' => json_encode(['entitlement_code' => 'advert_package']),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $oneOffId = DB::table('packages')->where('slug', 'advert-boost-30d')->value('id');
            $monthlyId = DB::table('packages')->where('slug', 'advert-boost-monthly')->value('id');

            DB::table('package_prices')->updateOrInsert(
                ['package_id' => $oneOffId],
                [
                    'currency' => 'ZAR',
                    'amount' => 450.00,
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
                    'amount' => 175.00,
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
            $query->select('id')->from('packages')->whereIn('slug', ['advert-boost-30d', 'advert-boost-monthly']);
        })->delete();

        DB::table('packages')->whereIn('slug', ['advert-boost-30d', 'advert-boost-monthly'])->delete();

        Schema::dropIfExists('ad_campaigns');
    }
};
