<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->string('locale', 12);
            $table->json('content');
            $table->string('source_locale', 12)->default('en');
            $table->string('source_hash', 64)->index();
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamp('translated_at')->nullable();
            $table->timestamps();

            $table->unique(['translatable_type', 'translatable_id', 'locale'], 'content_translations_unique_locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
    }
};
