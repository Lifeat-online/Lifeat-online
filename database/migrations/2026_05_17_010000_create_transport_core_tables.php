<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->insertOrIgnore([
            ['name' => 'Transport Manager', 'slug' => 'transport_manager', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transport Driver', 'slug' => 'transport_driver', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('transport_drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('phone')->nullable();
            $table->string('id_number')->nullable();
            $table->string('license_number')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->boolean('can_transport_people')->default(false);
            $table->boolean('can_transport_parcels')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_driver_id')->nullable()->constrained('transport_drivers')->nullOnDelete();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('vehicle_type')->default('bicycle')->index();
            $table->string('registration_number')->nullable();
            $table->string('status')->default('pending')->index();
            $table->boolean('can_carry_people')->default(false);
            $table->boolean('can_carry_parcels')->default(true);
            $table->unsignedSmallInteger('max_passengers')->default(0);
            $table->decimal('max_weight_kg', 8, 2)->nullable();
            $table->string('pricing_mode')->default('per_km');
            $table->decimal('base_fee', 10, 2)->default(0);
            $table->decimal('per_km_fee', 10, 2)->default(0);
            $table->decimal('per_person_fee', 10, 2)->default(0);
            $table->decimal('minimum_fee', 10, 2)->default(0);
            $table->decimal('waiting_fee', 10, 2)->default(0);
            $table->decimal('cancellation_fee', 10, 2)->default(0);
            $table->boolean('accepts_cash')->default(true);
            $table->boolean('has_card_machine')->default(false);
            $table->boolean('accepts_payfast')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transport_duty_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_driver_id')->constrained('transport_drivers')->cascadeOnDelete();
            $table->foreignId('transport_vehicle_id')->constrained('transport_vehicles')->restrictOnDelete();
            $table->string('status')->default('available')->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable()->index();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['transport_driver_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_duty_sessions');
        Schema::dropIfExists('transport_vehicles');
        Schema::dropIfExists('transport_drivers');

        DB::table('roles')->whereIn('slug', ['transport_manager', 'transport_driver'])->delete();
    }
};
