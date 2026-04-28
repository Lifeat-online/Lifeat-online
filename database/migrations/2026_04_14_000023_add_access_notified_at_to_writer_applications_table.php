<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('writer_applications', function (Blueprint $table) {
            $table->timestamp('access_notified_at')->nullable()->after('onboarded_at');
        });
    }

    public function down(): void
    {
        Schema::table('writer_applications', function (Blueprint $table) {
            $table->dropColumn('access_notified_at');
        });
    }
};
