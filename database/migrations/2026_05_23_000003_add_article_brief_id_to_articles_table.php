<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('articles', 'article_brief_id')) {
            return;
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('article_brief_id')
                ->nullable()
                ->after('id')
                ->constrained('article_briefs')
                ->nullOnDelete();
            $table->unique('article_brief_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('articles', 'article_brief_id')) {
            return;
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['article_brief_id']);
            $table->dropUnique(['article_brief_id']);
            $table->dropColumn('article_brief_id');
        });
    }
};
