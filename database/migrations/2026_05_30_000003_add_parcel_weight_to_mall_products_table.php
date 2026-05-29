<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mall_products', function (Blueprint $table) {
            $table->decimal('parcel_weight_kg', 8, 3)->nullable()->after('stock_qty');
        });

        Schema::table('mall_order_items', function (Blueprint $table) {
            $table->decimal('parcel_weight_kg', 8, 3)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('mall_order_items', function (Blueprint $table) {
            $table->dropColumn('parcel_weight_kg');
        });

        Schema::table('mall_products', function (Blueprint $table) {
            $table->dropColumn('parcel_weight_kg');
        });
    }
};
