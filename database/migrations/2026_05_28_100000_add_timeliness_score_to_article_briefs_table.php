<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_briefs', function (Blueprint $table) {
            $table->decimal('timeliness_score', 5, 2)->default(0)->after('newsworthiness_score');
        });
    }

    public function down(): void
    {
        Schema::table('article_briefs', function (Blueprint $table) {
            $table->dropColumn('timeliness_score');
        });
    }
};
