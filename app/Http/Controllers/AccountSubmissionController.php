<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Classified;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AccountSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();

        $submissions = $this->submissionItems($user->id)
            ->when($type !== '', fn (Collection $items) => $items->where('type', $type))
            ->when($status !== '', fn (Collection $items) => $items->where('status', $status))
            ->sortByDesc('timestamp')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $paginated = new LengthAwarePaginator(
            $submissions->forPage($page, $perPage)->values(),
            $submissions->count(),
            $perPage,
            $page,
            [
                'path' => route('account.submissions.index'),
                'query' => $request->query(),
            ]
        );

        $submissionCounts = [
            'total' => $this->submissionItems($user->id)->count(),
            'pending' => $this->submissionItems($user->id)->whereIn('status', ['pending', 'pending_review', 'revision_requested', 'draft'])->count(),
            'published' => $this->submissionItems($user->id)->where('status', 'published')->count(),
        ];

        return view('account.submissions.index', [
            'submissions' => $paginated,
            'filters' => [
                'type' => $type,
                'status' => $status,
            ],
            'submissionCounts' => $submissionCounts,
        ]);
    }

    private function submissionItems(int $userId): Collection
    {
        $listingItems = Listing::query()
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn (Listing $listing) => [
                'type' => 'listing',
                'title' => $listing->title,
                'status' => $listing->status,
                'location' => $listing->city,
                'timestamp' => $listing->updated_at ?? $listing->created_at,
                'feedback' => $listing->isPubliclyVisible()
                    ? 'Publicly visible with an active subscription.'
                    : ($listing->status === 'published'
                        ? 'Published status set, but public visibility still depends on an active subscription.'
                        : 'Listing is still being prepared for publication.'),
                'action_label' => 'Edit listing profile',
                'action_url' => route('account.listings.edit', $listing),
            ]);

        $classifiedItems = Classified::query()
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn (Classified $classified) => [
                'type' => 'classified',
                'title' => $classified->title,
                'status' => $classified->status,
                'location' => $classified->city,
                'timestamp' => $classified->reviewed_at ?? $classified->updated_at ?? $classified->created_at,
                'feedback' => $classified->moderation_notes ?: ($classified->status === Classified::STATUS_PUBLISHED
                    ? 'Approved and publicly visible.'
                    : 'Waiting on moderation review.'),
                'action_label' => $classified->status === Classified::STATUS_PUBLISHED ? 'View classified' : 'Edit classified',
                'action_url' => $classified->status === Classified::STATUS_PUBLISHED
                    ? route('classifieds.show', $classified)
                    : route('classifieds.manage.edit', $classified),
            ]);

        $articleItems = Article::with('revisionNotes')
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(fn (Article $article) => [
                'type' => 'article',
                'title' => $article->title,
                'status' => $article->status,
                'location' => null,
                'timestamp' => $article->submitted_at ?? $article->updated_at ?? $article->created_at,
                'feedback' => $article->revisionNotes->first()?->note
                    ?: ($article->status === 'published' ? 'Published and live on the site.' : 'No editorial feedback yet.'),
                'action_label' => $article->status === 'published' ? 'View article' : 'Edit submission',
                'action_url' => $article->status === 'published'
                    ? route('articles.show', $article)
                    : route('writer.articles.edit', $article),
            ]);

        return $listingItems->concat($classifiedItems)->concat($articleItems);
    }
}
