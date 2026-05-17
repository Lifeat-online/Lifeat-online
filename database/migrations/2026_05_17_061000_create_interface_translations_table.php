<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interface_translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 12);
            $table->string('source_hash', 64);
            $table->text('source_text');
            $table->text('translated_text');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->timestamp('translated_at')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'source_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_translations');
    }
};
