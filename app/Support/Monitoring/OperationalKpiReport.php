<?php

namespace App\Support\Monitoring;

use App\Models\AdCampaign;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\ArticleWordLedger;
use App\Models\Classified;
use App\Models\CivicFaultReport;
use App\Models\Listing;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\PushCampaign;
use App\Models\StaffWallet;
use App\Models\Subscription;
use App\Models\WriterApplication;
use App\Models\WriterPaymentBatch;

class OperationalKpiReport
{
    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return [
            'revenue' => $this->revenue(),
            'listings' => $this->listings(),
            'subscriptions' => $this->subscriptions(),
            'payments' => $this->payments(),
            'approval_queues' => $this->approvalQueues(),
            'writer_payouts' => $this->writerPayouts(),
            'staff_wallets' => $this->staffWallets(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revenue(): array
    {
        $paid = Payment::query()->where('status', 'paid');
        $paidTotal = (float) (clone $paid)->sum('amount');
        $paidLast30Days = (float) (clone $paid)->where('paid_at', '>=', now()->subDays(30))->sum('amount');
        $paidToday = (float) (clone $paid)->whereDate('paid_at', today())->sum('amount');

        return [
            'currency' => 'ZAR',
            'paid_total' => round($paidTotal, 2),
            'paid_total_display' => $this->money($paidTotal),
            'paid_last_30d' => round($paidLast30Days, 2),
            'paid_last_30d_display' => $this->money($paidLast30Days),
            'paid_today' => round($paidToday, 2),
            'paid_today_display' => $this->money($paidToday),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function listings(): array
    {
        return [
            'total' => Listing::query()->count(),
            'active' => Listing::published()->count(),
            'draft' => Listing::query()->where('status', 'draft')->count(),
            'published_without_active_subscription' => Listing::query()
                ->where('status', 'published')
                ->whereDoesntHave('activeSubscription', function ($subscription): void {
                    $subscription->where('status', 'active')
                        ->where(function ($query): void {
                            $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                        });
                })
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function subscriptions(): array
    {
        return [
            'active' => Subscription::query()
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })
                ->count(),
            'expiring_7d' => Subscription::query()
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [now(), now()->addDays(7)])
                ->count(),
            'expired_pending_sweep' => Subscription::query()
                ->whereIn('status', ['active', 'pending'])
                ->whereNotNull('ends_at')
                ->where('ends_at', '<', now())
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function payments(): array
    {
        return [
            'failed' => Payment::query()->where('status', 'failed')->count(),
            'failed_last_24h' => Payment::query()
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'pending_orders' => Order::query()->where('status', 'pending_payment')->count(),
            'stale_pending_orders' => Order::query()
                ->where('status', 'pending_payment')
                ->where('created_at', '<=', now()->subDay())
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function approvalQueues(): array
    {
        $queues = [
            'writer_applications' => WriterApplication::query()
                ->whereIn('status', [WriterApplication::STATUS_PENDING, WriterApplication::STATUS_UNDER_REVIEW])
                ->count(),
            'ad_campaigns' => AdCampaign::query()->where('status', 'ready')->count(),
            'push_campaigns' => PushCampaign::query()->where('status', 'ready')->whereNull('sent_at')->count(),
            'articles' => Article::query()->where('status', 'pending_review')->count(),
            'article_briefs' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_PENDING_REVIEW)->count(),
            'classifieds' => Classified::query()->where('status', Classified::STATUS_PENDING)->count(),
            'civic_faults' => CivicFaultReport::query()->where('is_approved', false)->count(),
            'notifications_attention' => NotificationLog::query()->whereIn('status', ['pending', 'queued', 'failed'])->count(),
        ];

        $queues['total'] = array_sum($queues);

        return $queues;
    }

    /**
     * @return array<string, mixed>
     */
    private function writerPayouts(): array
    {
        $pendingLedgers = ArticleWordLedger::query()->where('status', 'pending');
        $exportedBatches = WriterPaymentBatch::query()->where('status', 'exported');
        $pendingAmount = (float) (clone $pendingLedgers)->sum('gross_amount');
        $exportedAmount = (float) (clone $exportedBatches)->sum('gross_amount');

        return [
            'pending_ledgers' => (clone $pendingLedgers)->count(),
            'pending_amount' => round($pendingAmount, 2),
            'pending_amount_display' => $this->money($pendingAmount),
            'exported_batches' => (clone $exportedBatches)->count(),
            'exported_amount' => round($exportedAmount, 2),
            'exported_amount_display' => $this->money($exportedAmount),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffWallets(): array
    {
        $available = (float) StaffWallet::query()->sum('available_balance');
        $pending = (float) StaffWallet::query()->sum('pending_balance');
        $activePayoutAmount = (float) PayoutRequest::query()
            ->whereIn('status', PayoutRequest::activeStatuses())
            ->sum('amount');

        return [
            'wallets' => StaffWallet::query()->count(),
            'available_liability' => round($available, 2),
            'available_liability_display' => $this->money($available),
            'pending_liability' => round($pending, 2),
            'pending_liability_display' => $this->money($pending),
            'active_payout_requests' => PayoutRequest::query()->whereIn('status', PayoutRequest::activeStatuses())->count(),
            'active_payout_amount' => round($activePayoutAmount, 2),
            'active_payout_amount_display' => $this->money($activePayoutAmount),
        ];
    }

    private function money(float $amount): string
    {
        return 'R '.number_format($amount, 2);
    }
}
