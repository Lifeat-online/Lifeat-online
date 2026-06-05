<?php

namespace App\Support\Onboarding;

use App\Models\Article;
use App\Models\ArticleWordLedger;
use App\Models\User;
use App\Models\WriterApplication;

class WriterOnboardingChecklist
{
    public function forUser(User $user): array
    {
        $application = WriterApplication::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            })
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        $articleCounts = Article::query()
            ->where('user_id', $user->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $ledgerTotals = ArticleWordLedger::query()
            ->where('writer_user_id', $user->id)
            ->selectRaw('status, count(*) as item_count, coalesce(sum(gross_amount), 0) as gross_total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $hasSubmission = $articleCounts->sum() > 0;
        $hasReviewMovement = $articleCounts->except(['draft'])->sum() > 0;
        $hasPublished = (int) ($articleCounts['published'] ?? 0) > 0;
        $hasPayableLedger = $ledgerTotals->isNotEmpty();

        $steps = [
            [
                'label' => 'Application approved',
                'status' => $this->applicationStatus($application, $user),
                'detail' => $this->applicationDetail($application, $user),
                'action_label' => 'View account hub',
                'action_url' => route('account.index'),
            ],
            [
                'label' => 'First article draft',
                'status' => $hasSubmission ? 'done' : 'next',
                'detail' => $hasSubmission
                    ? 'Your writer workspace has at least one article submission.'
                    : 'Start with one focused local story. Save as draft if it is not ready for review yet.',
                'action_label' => $hasSubmission ? 'View submissions' : 'New submission',
                'action_url' => $hasSubmission ? route('writer.articles.index') : route('writer.articles.create'),
            ],
            [
                'label' => 'Editorial review',
                'status' => $hasReviewMovement ? 'done' : ($hasSubmission ? 'next' : 'pending'),
                'detail' => $hasReviewMovement
                    ? 'At least one article has entered the review or publishing workflow.'
                    : ($hasSubmission
                        ? 'Tick submit for review when your title, excerpt, body, category, and location are ready.'
                        : 'Editorial review begins after a saved article is submitted.'),
                'action_label' => 'View submissions',
                'action_url' => route('writer.articles.index'),
            ],
            [
                'label' => 'Publication and translation',
                'status' => $hasPublished ? 'done' : ($hasReviewMovement ? 'next' : 'pending'),
                'detail' => $hasPublished
                    ? 'At least one approved article is live or ready for public publication surfaces.'
                    : ($hasReviewMovement
                        ? 'Editors may approve, publish, request revisions, and trigger the bilingual publishing flow.'
                        : 'Publication only happens after editorial approval.'),
                'action_label' => 'Browse articles',
                'action_url' => route('articles.index'),
            ],
            [
                'label' => 'Earnings and payout',
                'status' => $hasPayableLedger ? 'done' : ($hasPublished ? 'next' : 'pending'),
                'detail' => $hasPayableLedger
                    ? $this->ledgerDetail($ledgerTotals)
                    : ($hasPublished
                        ? 'Published article earnings appear here after the word ledger is created and approved.'
                        : 'No payout details are needed until approved work creates a payable ledger entry.'),
                'action_label' => 'View earnings',
                'action_url' => route('writer.earnings.index'),
            ],
        ];

        return [
            'application' => $application,
            'article_counts' => $articleCounts,
            'ledger_totals' => $ledgerTotals,
            'completed' => collect($steps)->where('status', 'done')->count(),
            'total' => count($steps),
            'next' => collect($steps)->firstWhere('status', 'next') ?: collect($steps)->firstWhere('status', 'pending'),
            'steps' => $steps,
        ];
    }

    private function applicationStatus(?WriterApplication $application, User $user): string
    {
        if ($application?->status === WriterApplication::STATUS_APPROVED || $user->hasRole('writer')) {
            return 'done';
        }

        return $application ? 'next' : 'pending';
    }

    private function applicationDetail(?WriterApplication $application, User $user): string
    {
        if ($application?->status === WriterApplication::STATUS_APPROVED || $user->hasRole('writer')) {
            return 'Your writer access is active. Use this workspace for article drafts, review feedback, and earnings.';
        }

        if ($application) {
            return 'Application status: '.str_replace('_', ' ', $application->status).'. The team reviews samples, documents, and role fit before granting access.';
        }

        return 'Submit a staff or writer application before using the writer workflow.';
    }

    private function ledgerDetail($ledgerTotals): string
    {
        $pending = (float) ($ledgerTotals->get('pending')?->gross_total ?? 0);
        $batched = (float) ($ledgerTotals->get('batched')?->gross_total ?? 0);
        $paid = (float) ($ledgerTotals->get('paid')?->gross_total ?? 0);

        return 'Ledger totals: pending R'.number_format($pending, 2).', batched R'.number_format($batched, 2).', paid R'.number_format($paid, 2).'.';
    }
}
