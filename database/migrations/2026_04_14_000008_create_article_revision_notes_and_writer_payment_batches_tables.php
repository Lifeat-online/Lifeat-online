<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_revision_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->nullable();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('writer_payment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('exported')->index();
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('writer_payment_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('writer_payment_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_word_ledger_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique('article_word_ledger_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writer_payment_batch_items');
        Schema::dropIfExists('writer_payment_batches');
        Schema::dropIfExists('article_revision_notes');
    }
};
