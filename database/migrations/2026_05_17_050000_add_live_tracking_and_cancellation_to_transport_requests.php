<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_requests', function (Blueprint $table) {
            $table->decimal('passenger_latitude', 10, 7)->nullable()->after('dropoff_longitude');
            $table->decimal('passenger_longitude', 10, 7)->nullable()->after('passenger_latitude');
            $table->timestamp('passenger_location_seen_at')->nullable()->after('passenger_longitude');
            $table->decimal('cancellation_fee', 10, 2)->default(0)->after('driver_amount');
        });
    }

    public function down(): void
    {
        Schema::table('transport_requests', function (Blueprint $table) {
            $table->dropColumn([
                'passenger_latitude',
                'passenger_longitude',
                'passenger_location_seen_at',
                'cancellation_fee',
            ]);
        });
    }
};
