<?php

use App\Models\NumberSequence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);
            $table->string('prefix', 16)->nullable();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_value')->default(0);
            $table->unsignedTinyInteger('padding')->default(6);
            $table->timestamps();

            $table->unique(['key', 'year']);
        });

        NumberSequence::query()->updateOrCreate(
            ['key' => 'order', 'year' => (int) now()->format('Y')],
            ['prefix' => 'ORD', 'padding' => 6, 'last_value' => 0],
        );

        NumberSequence::query()->updateOrCreate(
            ['key' => 'invoice', 'year' => (int) now()->format('Y')],
            ['prefix' => 'INV', 'padding' => 6, 'last_value' => 0],
        );

        NumberSequence::query()->updateOrCreate(
            ['key' => 'writer_payment_batch', 'year' => (int) now()->format('Y')],
            ['prefix' => 'WPB', 'padding' => 6, 'last_value' => 0],
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
