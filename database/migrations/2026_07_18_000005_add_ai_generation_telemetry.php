<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_generations', function (Blueprint $table) {
            $table->uuid('trace_id')->nullable()->unique();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('finish_reason', 80)->nullable();
            $table->unsignedInteger('token_input_actual')->nullable();
            $table->unsignedInteger('token_output_actual')->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->string('error_category', 80)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('ai_generations', fn (Blueprint $table) => $table->dropColumn([
            'trace_id', 'latency_ms', 'finish_reason', 'token_input_actual', 'token_output_actual', 'cache_hit', 'error_category',
        ]));
    }
};
