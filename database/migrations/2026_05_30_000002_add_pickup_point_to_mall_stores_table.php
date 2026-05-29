<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mall_stores', function (Blueprint $table) {
            $table->string('pickup_address', 500)->nullable()->after('description');
            $table->decimal('pickup_latitude', 10, 7)->nullable()->after('pickup_address');
            $table->decimal('pickup_longitude', 10, 7)->nullable()->after('pickup_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('mall_stores', function (Blueprint $table) {
            $table->dropColumn(['pickup_address', 'pickup_latitude', 'pickup_longitude']);
        });
    }
};
