<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('editor_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('status');
        });

        Schema::create('article_word_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('writer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('word_count')->default(0);
            $table->decimal('rate_per_word', 10, 2)->default(0);
            $table->decimal('gross_amount', 10, 2)->default(0);
            $table->string('status')->default('pending')->index();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamps();

            $table->unique('article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_word_ledgers');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('editor_user_id');
            $table->dropColumn('submitted_at');
        });
    }
};
