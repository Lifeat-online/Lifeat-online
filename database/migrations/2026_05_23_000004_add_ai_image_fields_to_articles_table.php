<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            if (! Schema::hasColumn('articles', 'featured_image_caption')) {
                $table->string('featured_image_caption')->nullable()->after('featured_image');
            }

            if (! Schema::hasColumn('articles', 'featured_image_credit')) {
                $table->string('featured_image_credit')->nullable()->after('featured_image_caption');
            }

            if (! Schema::hasColumn('articles', 'featured_image_is_ai_generated')) {
                $table->boolean('featured_image_is_ai_generated')->default(false)->after('featured_image_credit');
            }

            if (! Schema::hasColumn('articles', 'featured_image_prompt')) {
                $table->text('featured_image_prompt')->nullable()->after('featured_image_is_ai_generated');
            }

            if (! Schema::hasColumn('articles', 'featured_image_provider')) {
                $table->string('featured_image_provider')->nullable()->after('featured_image_prompt');
            }

            if (! Schema::hasColumn('articles', 'featured_image_model')) {
                $table->string('featured_image_model')->nullable()->after('featured_image_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            foreach ([
                'featured_image_model',
                'featured_image_provider',
                'featured_image_prompt',
                'featured_image_is_ai_generated',
                'featured_image_credit',
                'featured_image_caption',
            ] as $column) {
                if (Schema::hasColumn('articles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
