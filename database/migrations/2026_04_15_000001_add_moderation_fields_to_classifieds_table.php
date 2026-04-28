<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classifieds', function (Blueprint $table) {
            $table->foreignId('reviewed_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('status')->index();
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->text('moderation_notes')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('classifieds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn([
                'submitted_at',
                'reviewed_at',
                'moderation_notes',
            ]);
        });
    }
};
