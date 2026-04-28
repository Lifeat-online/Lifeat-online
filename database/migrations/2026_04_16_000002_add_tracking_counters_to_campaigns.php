<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('impressions')->default(0)->after('published_at');
            $table->unsignedBigInteger('clicks')->default(0)->after('impressions');
        });

        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('open_count')->default(0)->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn(['impressions', 'clicks']);
        });

        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->dropColumn('open_count');
        });
    }
};
