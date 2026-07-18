<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_source_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_snapshot_id')->constrained()->cascadeOnDelete();
            $table->morphs('sourceable');
            $table->string('role', 30)->default('supporting');
            $table->timestamps();
            $table->unique(['source_snapshot_id', 'sourceable_type', 'sourceable_id'], 'content_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_source_links');
    }
};
