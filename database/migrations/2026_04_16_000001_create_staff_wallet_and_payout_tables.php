<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Commission attribution on orders ──────────────────────────────
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('referred_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // ── Staff wallet per sales_staff user ─────────────────────────────
        Schema::create('staff_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('ZAR');
            $table->decimal('available_balance', 12, 2)->default(0);
            $table->decimal('pending_balance', 12, 2)->default(0);
            $table->decimal('paid_out_total', 12, 2)->default(0);
            $table->timestamps();
        });

        // ── Payout request lifecycle ──────────────────────────────────────
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('staff_wallets')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->string('status')->default('requested')->index(); // requested|approved|paid|rejected|cancelled
            $table->string('bank_name')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('account_number')->nullable();
            $table->string('branch_code')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // ── Immutable ledger for credits and debits ────────────────────────
        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('staff_wallets')->cascadeOnDelete();
            $table->foreignId('payout_request_id')->nullable()->constrained('payout_requests')->nullOnDelete();
            $table->string('entry_type'); // commission_credit | payout_debit | adjustment
            $table->nullableMorphs('source'); // source_type / source_id (Payment, PayoutRequest …)
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->string('description')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });

        // ── Commission rate setting ────────────────────────────────────────
        $now = now();
        DB::table('settings')->insertOrIgnore([
            ['key' => 'commission.rate', 'value' => '0.10', 'type' => 'decimal', 'group' => 'commission', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger_entries');
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('staff_wallets');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_user_id');
        });

        DB::table('settings')->where('key', 'commission.rate')->delete();
    }
};
