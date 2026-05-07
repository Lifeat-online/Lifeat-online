<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('voucher_type')->default('discount_amount')->index();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('usage_limit')->default(1);
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->dateTime('start_at')->nullable()->index();
            $table->dateTime('end_at')->nullable()->index();
            $table->longText('terms')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedTinyInteger('last_usage_threshold_notified')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'slug']);
        });

        Schema::create('voucher_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['voucher_id', 'category_id']);
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('claimed')->index();
            $table->timestamp('claimed_at')->nullable()->index();
            $table->timestamp('consumed_at')->nullable()->index();
            $table->foreignId('consumed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('consumed_ip')->nullable();
            $table->string('consumed_user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['voucher_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('voucher_category');
        Schema::dropIfExists('vouchers');
    }
};

