<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_order_id')->unique()->constrained('mall_orders')->cascadeOnDelete();
            $table->string('provider', 30)->index();
            $table->string('label', 120);
            $table->string('status', 30)->default('pending')->index();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('platform_fee', 10, 2)->default(0);
            $table->decimal('provider_amount', 10, 2)->default(0);
            $table->string('delivery_area', 30)->default('local');
            $table->text('delivery_address')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('external_type', 60)->nullable();
            $table->unsignedBigInteger('external_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['external_type', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_fulfillments');
    }
};
