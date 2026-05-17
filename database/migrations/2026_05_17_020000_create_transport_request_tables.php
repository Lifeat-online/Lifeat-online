<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transport_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accepted_transport_driver_id')->nullable()->constrained('transport_drivers')->nullOnDelete();
            $table->foreignId('accepted_transport_vehicle_id')->nullable()->constrained('transport_vehicles')->nullOnDelete();
            $table->string('request_number')->unique();
            $table->string('service_type')->index();
            $table->string('status')->default('dispatching')->index();
            $table->string('payment_method')->default('payfast')->index();
            $table->string('pickup_address');
            $table->string('dropoff_address');
            $table->decimal('pickup_latitude', 10, 7)->nullable();
            $table->decimal('pickup_longitude', 10, 7)->nullable();
            $table->decimal('dropoff_latitude', 10, 7)->nullable();
            $table->decimal('dropoff_longitude', 10, 7)->nullable();
            $table->decimal('distance_km', 8, 2);
            $table->unsignedSmallInteger('passenger_count')->default(0);
            $table->decimal('parcel_weight_kg', 8, 2)->nullable();
            $table->string('required_vehicle_type')->nullable();
            $table->text('client_notes')->nullable();
            $table->decimal('quoted_amount', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('driver_amount', 10, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'service_type']);
        });

        Schema::create('transport_request_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_request_id')->constrained('transport_requests')->cascadeOnDelete();
            $table->foreignId('transport_driver_id')->constrained('transport_drivers')->cascadeOnDelete();
            $table->foreignId('transport_vehicle_id')->constrained('transport_vehicles')->cascadeOnDelete();
            $table->foreignId('transport_duty_session_id')->constrained('transport_duty_sessions')->cascadeOnDelete();
            $table->string('status')->default('offered')->index();
            $table->decimal('quoted_amount', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('driver_amount', 10, 2);
            $table->timestamp('offered_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();
            $table->unique(['transport_request_id', 'transport_driver_id']);
        });

        Schema::create('transport_request_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_request_id')->constrained('transport_requests')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['transport_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_request_status_events');
        Schema::dropIfExists('transport_request_offers');
        Schema::dropIfExists('transport_requests');
    }
};
