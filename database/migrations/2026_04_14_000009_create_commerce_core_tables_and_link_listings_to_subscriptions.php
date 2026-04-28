<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('billing_model')->default('six_monthly')->index();
            $table->boolean('is_self_service')->default(false);
            $table->unsignedInteger('duration_days')->default(180);
            $table->string('status')->default('active')->index();
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });

        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 8)->default('ZAR');
            $table->decimal('amount', 12, 2);
            $table->boolean('vat_inclusive')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default('draft')->index();
            $table->string('currency', 8)->default('ZAR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purchasable_type')->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();
            $table->string('name_snapshot');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('billing_model')->default('once_off');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['purchasable_type', 'purchasable_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('invoice_prefix_snapshot')->default('LIFE');
            $table->string('status')->default('draft')->index();
            $table->string('currency', 8)->default('ZAR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('manual');
            $table->string('status')->default('pending')->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 8)->default('ZAR');
            $table->string('provider_transaction_id')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->string('subscribable_type')->nullable();
            $table->unsignedBigInteger('subscribable_id')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('renews_at')->nullable();
            $table->string('renewal_mode')->default('manual');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['subscribable_type', 'subscribable_id']);
        });

        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('entitled_type');
            $table->unsignedBigInteger('entitled_id');
            $table->string('entitlement_code')->index();
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_until')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamps();

            $table->index(['entitled_type', 'entitled_id']);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->string('source_channel')->nullable()->after('user_id');
            $table->timestamp('package_expires_at')->nullable()->after('published_at');
            $table->unsignedBigInteger('active_subscription_id')->nullable()->after('package_expires_at');
        });

        $now = now();
        DB::table('package_types')->insert([
            ['name' => 'Business Directory', 'slug' => 'business_directory', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Event Package', 'slug' => 'event_package', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Advert Package', 'slug' => 'advert_package', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Push Campaign', 'slug' => 'push_campaign', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $businessTypeId = DB::table('package_types')->where('slug', 'business_directory')->value('id');

        DB::table('packages')->insert([
            [
                'package_type_id' => $businessTypeId,
                'name' => 'Business Directory Standard',
                'slug' => 'business-directory-standard-6m',
                'description' => 'Standard business directory package billed every 6 months.',
                'billing_model' => 'six_monthly',
                'is_self_service' => false,
                'duration_days' => 180,
                'status' => 'active',
                'settings_json' => json_encode(['entitlement_code' => 'business_directory']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'package_type_id' => $businessTypeId,
                'name' => 'Business Directory Self-Service',
                'slug' => 'business-directory-self-service-6m',
                'description' => 'Self-service business directory package billed every 6 months.',
                'billing_model' => 'six_monthly',
                'is_self_service' => true,
                'duration_days' => 180,
                'status' => 'active',
                'settings_json' => json_encode(['entitlement_code' => 'business_directory']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $standardId = DB::table('packages')->where('slug', 'business-directory-standard-6m')->value('id');
        $selfServiceId = DB::table('packages')->where('slug', 'business-directory-self-service-6m')->value('id');

        DB::table('package_prices')->insert([
            [
                'package_id' => $standardId,
                'currency' => 'ZAR',
                'amount' => 500.00,
                'vat_inclusive' => true,
                'effective_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'package_id' => $selfServiceId,
                'currency' => 'ZAR',
                'amount' => 750.00,
                'vat_inclusive' => true,
                'effective_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['source_channel', 'package_expires_at', 'active_subscription_id']);
        });

        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('package_prices');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('package_types');
    }
};
