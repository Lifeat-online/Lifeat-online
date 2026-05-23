<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_generations')) {
            return;
        }

        Schema::table('ai_generations', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_generations', 'input_payload')) {
                $table->json('input_payload')->nullable()->after('input_summary');
            }

            if (! Schema::hasColumn('ai_generations', 'retry_of_id')) {
                $table->unsignedBigInteger('retry_of_id')->nullable()->after('input_payload')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_generations')) {
            return;
        }

        Schema::table('ai_generations', function (Blueprint $table) {
            if (Schema::hasColumn('ai_generations', 'retry_of_id')) {
                $table->dropColumn('retry_of_id');
            }

            if (Schema::hasColumn('ai_generations', 'input_payload')) {
                $table->dropColumn('input_payload');
            }
        });
    }
};
