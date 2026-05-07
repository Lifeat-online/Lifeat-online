<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('registered_by_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->string('placement')->default('banner')->after('creative_image');
            $table->decimal('budget_amount', 12, 2)->nullable()->after('placement');
            $table->string('budget_currency', 8)->default('ZAR')->after('budget_amount');
            $table->json('targeting_json')->nullable()->after('budget_currency');
            $table->json('popup_settings_json')->nullable()->after('targeting_json');
        });

        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->decimal('budget_amount', 12, 2)->nullable()->after('message');
            $table->string('budget_currency', 8)->default('ZAR')->after('budget_amount');
        });

        Schema::create('marketing_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('provider')->nullable();
            $table->string('status')->default('inactive')->index();
            $table->json('settings_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['listing_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_integrations');

        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->dropColumn(['budget_amount', 'budget_currency']);
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn(['placement', 'budget_amount', 'budget_currency', 'targeting_json', 'popup_settings_json']);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registered_by_user_id');
        });
    }
};

