<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_requests', function (Blueprint $table) {
            $table->string('request_timing')->default('immediate')->after('payment_method')->index();
            $table->timestamp('scheduled_pickup_at')->nullable()->after('request_timing')->index();
            $table->timestamp('dispatch_started_at')->nullable()->after('scheduled_pickup_at');
        });
    }

    public function down(): void
    {
        Schema::table('transport_requests', function (Blueprint $table) {
            $table->dropColumn(['request_timing', 'scheduled_pickup_at', 'dispatch_started_at']);
        });
    }
};
