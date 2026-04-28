<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('responded_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->text('owner_response')->nullable()->after('body');
            $table->timestamp('owner_responded_at')->nullable()->after('owner_response');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responded_by_user_id');
            $table->dropColumn([
                'owner_response',
                'owner_responded_at',
            ]);
        });
    }
};
