<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('councillors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('office_address')->nullable();
            $table->json('portfolios')->nullable();
            $table->json('category_responsibilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('councillor_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('councillor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('geojson');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('civic_fault_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_councillor_id')->nullable()->constrained('councillors')->nullOnDelete();
            $table->string('category');
            $table->string('severity');
            $table->string('status')->default('reported');
            $table->string('address_label')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('description', 500);
            $table->timestamp('consented_at');
            $table->boolean('is_approved')->default(false);
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('in_progress_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['is_approved', 'status']);
            $table->index(['category', 'status']);
            $table->index(['assigned_councillor_id', 'status']);
            $table->index(['created_at']);
        });

        Schema::create('civic_fault_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('civic_fault_report_id')->constrained('civic_fault_reports')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('civic_fault_photos');
        Schema::dropIfExists('civic_fault_reports');
        Schema::dropIfExists('councillor_areas');
        Schema::dropIfExists('councillors');
    }
};
