<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_store_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('icon', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('mall_stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('tagline', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('banner_path')->nullable();
            $table->string('primary_color', 7)->default('#3B82F6');
            $table->string('payfast_merchant_id', 20)->nullable();
            $table->string('payfast_merchant_key', 20)->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('mall_store_category_mall_store', function (Blueprint $table) {
            $table->foreignId('mall_store_id')->constrained('mall_stores')->cascadeOnDelete();
            $table->foreignId('mall_store_category_id')->constrained('mall_store_categories')->cascadeOnDelete();
            $table->primary(['mall_store_id', 'mall_store_category_id'], 'mall_store_category_store_primary');
        });

        Schema::create('mall_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_store_id')->constrained('mall_stores')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('slug', 220);
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->string('sku', 100)->nullable();
            $table->unsignedInteger('stock_qty')->default(0);
            $table->boolean('manage_stock')->default(true);
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('images')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['mall_store_id', 'slug']);
            $table->index('mall_store_id');
        });

        Schema::create('mall_product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_store_id')->constrained('mall_stores')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['mall_store_id', 'slug']);
        });

        Schema::create('mall_product_mall_product_category', function (Blueprint $table) {
            $table->foreignId('mall_product_id')->constrained('mall_products')->cascadeOnDelete();
            $table->foreignId('mall_product_category_id')->constrained('mall_product_categories')->cascadeOnDelete();
            $table->primary(['mall_product_id', 'mall_product_category_id'], 'mall_product_category_primary');
        });

        Schema::create('mall_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('mall_store_id')->constrained('mall_stores')->cascadeOnDelete();
            $table->string('session_token', 64)->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'mall_store_id']);
            $table->unique(['session_token', 'mall_store_id']);
            $table->index('mall_store_id');
        });

        Schema::create('mall_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_cart_id')->constrained('mall_carts')->cascadeOnDelete();
            $table->foreignId('mall_product_id')->constrained('mall_products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->unique(['mall_cart_id', 'mall_product_id']);
        });

        Schema::create('mall_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mall_store_id')->constrained('mall_stores')->cascadeOnDelete();
            $table->string('order_number', 30)->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('vendor_amount', 10, 2);
            $table->text('customer_notes')->nullable();
            $table->string('payfast_payment_id', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('mall_store_id');
        });

        Schema::create('mall_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_order_id')->constrained('mall_orders')->cascadeOnDelete();
            $table->foreignId('mall_product_id')->nullable()->constrained('mall_products')->nullOnDelete();
            $table->string('product_name', 200);
            $table->string('product_sku', 100)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();

            $table->index('mall_order_id');
        });

        Schema::create('mall_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_order_id')->constrained('mall_orders')->cascadeOnDelete();
            $table->string('m_payment_id', 100)->unique();
            $table->string('payfast_payment_id', 50)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('status', 30)->default('initiated')->index();
            $table->json('itn_payload')->nullable();
            $table->decimal('payfast_fee', 10, 2)->nullable();
            $table->decimal('net_amount', 10, 2)->nullable();
            $table->timestamps();

            $table->index('mall_order_id');
        });

        Schema::create('mall_vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_store_id')->unique()->constrained('mall_stores')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('contact_name', 100);
            $table->string('contact_email', 150);
            $table->string('contact_phone', 20)->nullable();
            $table->string('business_reg', 50)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account', 30)->nullable();
            $table->string('bank_branch_code', 10)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_vendor_profiles');
        Schema::dropIfExists('mall_payments');
        Schema::dropIfExists('mall_order_items');
        Schema::dropIfExists('mall_orders');
        Schema::dropIfExists('mall_cart_items');
        Schema::dropIfExists('mall_carts');
        Schema::dropIfExists('mall_product_mall_product_category');
        Schema::dropIfExists('mall_product_categories');
        Schema::dropIfExists('mall_products');
        Schema::dropIfExists('mall_store_category_mall_store');
        Schema::dropIfExists('mall_stores');
        Schema::dropIfExists('mall_store_categories');
    }
};
