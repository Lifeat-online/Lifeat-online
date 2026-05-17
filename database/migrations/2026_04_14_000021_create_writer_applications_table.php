<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('writer_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('username');
            $table->text('profile_bio');
            $table->string('profile_photo_path')->nullable();
            $table->boolean('available_on_whatsapp')->default(false);
            $table->string('sample_article_title');
            $table->longText('sample_article_body');
            $table->string('sample_advert_title');
            $table->longText('sample_advert_body');
            $table->string('id_document_path')->nullable();
            $table->string('banking_document_path')->nullable();
            $table->string('proof_of_residence_path')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number', 60)->nullable();
            $table->string('branch_code', 30)->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('submitted_at');
            $table->index(['email', 'status']);
            $table->index(['username', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writer_applications');
    }
};
