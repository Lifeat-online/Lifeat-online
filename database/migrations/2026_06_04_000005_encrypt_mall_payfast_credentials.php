<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mall_stores')) {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('ALTER TABLE mall_stores MODIFY payfast_merchant_id TEXT NULL');
                DB::statement('ALTER TABLE mall_stores MODIFY payfast_merchant_key TEXT NULL');
            } else {
                Schema::table('mall_stores', function (Blueprint $table) {
                    $table->text('payfast_merchant_id')->nullable()->change();
                    $table->text('payfast_merchant_key')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mall_stores')) {
            $connection = DB::connection();
            $driver = $connection->getDriverName();

            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('ALTER TABLE mall_stores MODIFY payfast_merchant_id VARCHAR(20) NULL');
                DB::statement('ALTER TABLE mall_stores MODIFY payfast_merchant_key VARCHAR(20) NULL');
            } else {
                Schema::table('mall_stores', function (Blueprint $table) {
                    $table->string('payfast_merchant_id', 20)->nullable()->change();
                    $table->string('payfast_merchant_key', 20)->nullable()->change();
                });
            }
        }
    }
};
