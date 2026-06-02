<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\ArticleBrief;
use App\Models\ArticleWordLedger;
use App\Models\Classified;
use App\Models\CivicFaultReport;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\PushCampaign;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WriterApplication;
use App\Models\WriterPaymentBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminActionStationService
{
    public function __construct(
        private readonly ActionStationContentReviewService $contentReview,
    ) {
    }

    public function dashboard(User $user): array
    {
        $sections = collect([
            $this->contentReviewQueue($user),
            $this->deniedContentQueue($user),
            $this->humanContentQueue($user),
            $this->advertisingQueue($user),
            $this->financeQueue($user),
            $this->writerPaymentQueue($user),
            $this->payoutQueue($user),
        ])->filter(fn (?array $section): bool => $section !== null)->values();

        return [
            'settings' => $this->contentReview->settings(),
            'summary' => $this->summary($sections),
            'sections' => $sections,
            'approvedContent' => $this->approvedContentReport($user),
        ];
    }

    private function contentReviewQueue(User $user): ?array
    {
        if (! $user->hasRole('admin', 'editor')) {
            return null;
        }

        $items = $this->contentReview->pendingSources(20)
            ->map(fn (Model $source): array => [
                'id' => $this->contentReview->typeFor($source).'-'.$source->getKey(),
                'title' => $this->contentReview->titleFor($source),
                'type' => $this->contentReview->labelFor($source),
                'status' => (string) ($source->status ?? 'draft'),
                'meta' => $this->contentMeta($source),
                'href' => $this->contentReview->detailUrl($source),
                'action_label' => 'Review with AI',
                'review_type' => $this->contentReview->typeFor($source),
                'review_id' => $source->getKey(),
                'priority' => 'medium',
            ])
            ->values();

        return $this->section(
            'ai_review',
            'AI Content Review',
            'Draft public content waiting for AI grading. Approved items can be auto-published when enabled.',
            $items,
            'content'
        );
    }

    private function deniedContentQueue(User $user): ?array
    {
        if (! $user->hasRole('admin', 'editor', 'staff')) {
            return null;
        }

        $items = AiGeneration::query()
            ->with('source')
            ->where('feature_key', ActionStationContentReviewService::FEATURE_KEY)
            ->whereIn('status', [AiGeneration::STATUS_REJECTED, AiGeneration::STATUS_FAILED])
            ->latest()
            ->limit(20)
            ->get()
            ->filter(fn (AiGeneration $generation): bool => $generation->source instanceof Model)
            ->filter(fn (AiGeneration $generation): bool => $this->contentReview->latestReview($generation->source)?->is($generation) ?? false)
            ->map(function (AiGeneration $generation): array {
                $source = $generation->source;
                $payload = (array) ($generation->output_payload ?? []);
                $flags = collect($payload['blocking_flags'] ?? $payload['eligibility_blockers'] ?? [])
                    ->filter()
                    ->take(2)
                    ->implode(', ');

                return [
                    'id' => 'denied-'.$generation->id,
                    'title' => $this->contentReview->titleFor($source),
                    'type' => $this->contentReview->labelFor($source),
                    'status' => Str::headline((string) ($payload['recommendation'] ?? $generation->status)),
                    'meta' => $flags !== '' ? $flags : ($generation->error_message ?: 'Needs a human decision before publication.'),
                    'href' => $this->contentReview->detailUrl($source),
                    'action_label' => 'Open item',
                    'priority' => $generation->status === AiGeneration::STATUS_FAILED ? 'high' : 'medium',
                ];
            })
            ->values();

        return $this->section(
            'denied_content',
            'Denied Or Blocked Content',
            'AI-rejected, failed, or human-review public content that needs operator handling.',
            $items,
            'content'
        );
    }

    private function humanContentQueue(User $user): ?array
    {
        if (! $user->hasRole('admin', 'editor', 'staff')) {
            return null;
        }

        $items = collect();

        if ($user->hasRole('admin', 'editor', 'staff')) {
            $items = $items->concat(Classified::with(['user'])
                ->where('status', Classified::STATUS_PENDING)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Classified $classified): array => [
                    'id' => 'classified-'.$classified->id,
                    'title' => $classified->title,
                    'type' => 'Classified',
                    'status' => 'Pending moderation',
                    'meta' => $classified->city ?: ($classified->user?->email ?: 'Submitted classified'),
                    'href' => route('admin.classifieds.show', $classified),
                    'action_label' => 'Moderate',
                    'priority' => 'medium',
                ]));
        }

        if ($user->hasRole('admin', 'editor')) {
            $items = $items->concat(CivicFaultReport::with(['reporter'])
                ->where(function ($query) {
                    $query->where('is_approved', false)
                        ->orWhere('severity', CivicFaultReport::SEVERITY_URGENT)
                        ->orWhereNull('assigned_councillor_id');
                })
                ->where('status', '!=', CivicFaultReport::STATUS_RESOLVED)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (CivicFaultReport $report): array => [
                    'id' => 'fault-'.$report->id,
                    'title' => Str::limit($report->description, 72),
                    'type' => 'Civic Fault',
                    'status' => $report->is_approved ? Str::headline($report->status) : 'Pending approval',
                    'meta' => trim(($report->severity ?: 'medium').' '.$report->category.' '.$report->address_label),
                    'href' => route('admin.fault-reports.show', $report),
                    'action_label' => 'Triage',
                    'priority' => $report->severity === CivicFaultReport::SEVERITY_URGENT ? 'high' : 'medium',
                ]));

            $items = $items->concat(Article::with(['author'])
                ->where('status', 'pending_review')
                ->latest('submitted_at')
                ->limit(8)
                ->get()
                ->map(fn (Article $article): array => [
                    'id' => 'article-'.$article->id,
                    'title' => $article->title,
                    'type' => 'Writer Article',
                    'status' => 'Human editorial review',
                    'meta' => $article->author?->name ?: 'Staff writer submission',
                    'href' => route('admin.articles.edit', $article),
                    'action_label' => 'Review article',
                    'priority' => 'medium',
                ]));

            $items = $items->concat(ArticleBrief::where('status', ArticleBrief::STATUS_PENDING_REVIEW)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (ArticleBrief $brief): array => [
                    'id' => 'brief-'.$brief->id,
                    'title' => $brief->title,
                    'type' => 'Editorial Brief',
                    'status' => 'Human approval',
                    'meta' => 'AI-generated brief needs editor approval before Jimmy writes.',
                    'href' => route('admin.article-briefs.index', ['status' => ArticleBrief::STATUS_PENDING_REVIEW]),
                    'action_label' => 'Review brief',
                    'priority' => 'medium',
                ]));

            $items = $items->concat(WriterApplication::with('user')
                ->whereIn('status', [WriterApplication::STATUS_PENDING, WriterApplication::STATUS_UNDER_REVIEW])
                ->latest('submitted_at')
                ->limit(8)
                ->get()
                ->map(fn (WriterApplication $application): array => [
                    'id' => 'writer-app-'.$application->id,
                    'title' => $application->full_name,
                    'type' => 'Writer Application',
                    'status' => Str::headline($application->status),
                    'meta' => $application->email,
                    'href' => route('admin.writer-applications.show', $application),
                    'action_label' => 'Review application',
                    'priority' => 'medium',
                ]));
        }

        return $this->section(
            'human_content',
            'Human Content Decisions',
            'Moderation and paid-writing workflows that should stay human-reviewed.',
            $items->take(24)->values(),
            'content'
        );
    }

    private function advertisingQueue(User $user): ?array
    {
        if (! $user->hasRole('admin', 'editor')) {
            return null;
        }

        $items = PushCampaign::with(['listing'])
            ->whereNull('sent_at')
            ->whereIn('status', ['ready', 'scheduled', 'active'])
            ->latest()
            ->limit(12)
            ->get()
            ->map(fn (PushCampaign $campaign): array => [
                'id' => 'push-'.$campaign->id,
                'title' => $campaign->title,
                'type' => 'Push Campaign',
                'status' => $campaign->status === 'ready' ? 'Needs human send decision' : Str::headline($campaign->status),
                'meta' => ($campaign->listing?->title ?: 'No listing').' · '.$campaign->audienceSummary(),
                'href' => route('admin.campaigns.push.show', $campaign),
                'action_label' => 'Open campaign',
                'priority' => 'high',
            ]);

        return $this->section(
            'advertising',
            'Outbound Campaigns',
            'Push sends stay human-controlled even when copy has been AI-assisted.',
            $items,
            'advertising'
        );
    }

    private function financeQueue(User $user): ?array
    {
        if (! $user->hasRole('admin', 'editor', 'support')) {
            return null;
        }

        $items = collect()
            ->concat(Payment::with(['order.user'])
                ->whereIn('status', ['failed', 'pending'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Payment $payment): array => [
                    'id' => 'payment-'.$payment->id,
                    'title' => 'Payment '.$payment->id.' · '.$payment->currency.' '.number_format((float) $payment->amount, 2),
                    'type' => 'Payment',
                    'status' => Str::headline($payment->status),
                    'meta' => $payment->order?->order_number.' · '.($payment->user?->email ?: 'No customer'),
                    'href' => route('admin.finance.payments.show', $payment),
                    'action_label' => 'Reconcile',
                    'priority' => $payment->status === 'failed' ? 'high' : 'medium',
                ]))
            ->concat(Order::with(['user'])
                ->where('status', 'pending_payment')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Order $order): array => [
                    'id' => 'order-'.$order->id,
                    'title' => $order->order_number,
                    'type' => 'Order',
                    'status' => 'Pending payment',
                    'meta' => ($order->user?->email ?: 'No customer').' · '.$order->currency.' '.number_format((float) $order->total, 2),
                    'href' => route('admin.finance.orders.show', $order),
                    'action_label' => 'Inspect order',
                    'priority' => 'medium',
                ]))
            ->concat(NotificationLog::query()
                ->whereIn('status', ['pending', 'queued', 'failed'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (NotificationLog $notification): array => [
                    'id' => 'notification-'.$notification->id,
                    'title' => Str::headline($notification->notification_type),
                    'type' => 'Notification',
                    'status' => Str::headline($notification->status),
                    'meta' => ($notification->recipient ?: 'No recipient').' · '.$notification->channel,
                    'href' => route('admin.finance.notifications.show', $notification),
                    'action_label' => 'Review delivery',
                    'priority' => $notification->status === 'failed' ? 'high' : 'medium',
                ]))
            ->concat(Subscription::with(['user', 'package'])
                ->where('status', 'active')
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [now(), now()->addDays(7)])
                ->orderBy('ends_at')
                ->limit(8)
                ->get()
                ->map(fn (Subscription $subscription): array => [
                    'id' => 'subscription-'.$subscription->id,
                    'title' => $subscription->package?->name ?: 'Subscription '.$subscription->id,
                    'type' => 'Subscription',
                    'status' => 'Ending soon',
                    'meta' => ($subscription->user?->email ?: 'No customer').' · Ends '.($subscription->ends_at?->format('j M Y') ?: '-'),
                    'href' => route('admin.finance.subscriptions.show', $subscription),
                    'action_label' => 'Review renewal',
                    'priority' => 'medium',
                ]));

        return $this->section(
            'finance',
            'Finance And Support Exceptions',
            'Payment, order, notification, and subscription items that need human control.',
            $items->take(28)->values(),
            'finance'
        );
    }

    private function writerPaymentQueue(User $user): ?array
    {
        if (! $user->hasRole('admin', 'editor')) {
            return null;
        }

        $items = collect()
            ->concat(ArticleWordLedger::with(['article', 'writer'])
                ->where('status', 'pending')
                ->latest('approved_at')
                ->limit(12)
                ->get()
                ->map(fn (ArticleWordLedger $ledger): array => [
                    'id' => 'ledger-'.$ledger->id,
                    'title' => $ledger->article?->title ?: 'Writer ledger '.$ledger->id,
                    'type' => 'Writer Ledger',
                    'status' => 'Human payout approval',
                    'meta' => ($ledger->writer?->name ?: 'Writer').' · R '.number_format((float) $ledger->gross_amount, 2),
                    'href' => route('admin.writer-payments.index'),
                    'action_label' => 'Open writer payments',
                    'priority' => 'high',
                ]))
            ->concat(WriterPaymentBatch::with(['creator'])
                ->where('status', 'exported')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (WriterPaymentBatch $batch): array => [
                    'id' => 'writer-batch-'.$batch->id,
                    'title' => $batch->reference,
                    'type' => 'Writer Payment Batch',
                    'status' => 'Human mark-paid required',
                    'meta' => $batch->item_count.' item(s) · R '.number_format((float) $batch->gross_amount, 2),
                    'href' => route('admin.writer-payments.index'),
                    'action_label' => 'Open batch',
                    'priority' => 'high',
                ]));

        return $this->section(
            'writer_payments',
            'Writer Payments',
            'Staff writers are paid for writing. These approvals stay human-only.',
            $items,
            'payouts'
        );
    }

    private function payoutQueue(User $user): ?array
    {
        if (! $user->hasRole('admin', 'editor', 'support')) {
            return null;
        }

        $items = PayoutRequest::with(['requestedBy', 'wallet.user'])
            ->whereIn('status', PayoutRequest::activeStatuses())
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (PayoutRequest $request): array => [
                'id' => 'payout-'.$request->id,
                'title' => ($request->requestedBy?->name ?: 'Staff payout').' · '.$request->currency.' '.number_format((float) $request->amount, 2),
                'type' => 'Payout Request',
                'status' => Str::headline($request->status),
                'meta' => $request->bank_name ?: 'Bank details supplied',
                'href' => route('admin.payout-requests.show', $request),
                'action_label' => 'Process payout',
                'priority' => 'high',
            ]);

        return $this->section(
            'payouts',
            'Payout Requests',
            'Payout approvals and paid confirmations always require a human operator.',
            $items,
            'payouts'
        );
    }

    private function approvedContentReport(User $user): Collection
    {
        if (! $user->hasRole('admin', 'editor', 'staff')) {
            return collect();
        }

        return AiGeneration::query()
            ->with('source')
            ->where('feature_key', ActionStationContentReviewService::FEATURE_KEY)
            ->where('status', AiGeneration::STATUS_ACCEPTED)
            ->latest('reviewed_at')
            ->latest()
            ->limit(20)
            ->get()
            ->filter(fn (AiGeneration $generation): bool => $generation->source instanceof Model)
            ->filter(fn (AiGeneration $generation): bool => $this->contentReview->latestReview($generation->source)?->is($generation) ?? false)
            ->map(function (AiGeneration $generation): array {
                $source = $generation->source;
                $payload = (array) ($generation->output_payload ?? []);

                return [
                    'id' => $generation->id,
                    'title' => $this->contentReview->titleFor($source),
                    'type' => $this->contentReview->labelFor($source),
                    'status' => Str::headline($this->contentReview->publishState($source)),
                    'score' => min(
                        (int) ($payload['quality_score'] ?? 0),
                        (int) ($payload['safety_score'] ?? 0)
                    ),
                    'reviewed_at' => $generation->reviewed_at ?: $generation->updated_at,
                    'href' => $this->contentReview->detailUrl($source),
                    'summary' => (string) ($payload['public_summary'] ?? ''),
                ];
            })
            ->values();
    }

    private function summary(Collection $sections): array
    {
        $contentReview = $sections->firstWhere('key', 'ai_review');
        $denied = $sections->firstWhere('key', 'denied_content');

        return [
            'total_actions' => $sections->sum('count'),
            'high_priority' => $sections->sum(fn (array $section): int => collect($section['items'])->where('priority', 'high')->count()),
            'ai_review_pending' => (int) ($contentReview['count'] ?? 0),
            'denied_content' => (int) ($denied['count'] ?? 0),
            'approved_last_7d' => AiGeneration::where('feature_key', ActionStationContentReviewService::FEATURE_KEY)
                ->where('status', AiGeneration::STATUS_ACCEPTED)
                ->where('reviewed_at', '>=', now()->subDays(7))
                ->count(),
            'human_money_actions' => $sections
                ->whereIn('key', ['finance', 'writer_payments', 'payouts'])
                ->sum('count'),
        ];
    }

    private function section(string $key, string $title, string $description, Collection $items, string $group): array
    {
        return [
            'key' => $key,
            'group' => $group,
            'title' => $title,
            'description' => $description,
            'count' => $items->count(),
            'items' => $items->values(),
        ];
    }

    private function contentMeta(Model $source): string
    {
        return match (true) {
            $source instanceof AdCampaign => trim(($source->listing?->title ?: 'No listing').' · '.$source->placement),
            isset($source->city) && $source->city => (string) $source->city,
            isset($source->listing) && $source->listing => (string) $source->listing?->title,
            default => 'Updated '.($source->updated_at?->diffForHumans() ?: 'recently'),
        };
    }
}
