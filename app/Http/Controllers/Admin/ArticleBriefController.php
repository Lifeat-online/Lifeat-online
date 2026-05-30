<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArticleBrief;
use App\Models\Category;
use App\Services\JimmyWritingService;
use App\Support\Editorial\BriefFreshness;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleBriefController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: ArticleBrief::STATUS_PENDING_REVIEW;
        $search = trim((string) $request->string('q'));

        $query = ArticleBrief::query()
            ->with(['researchItem.researchSource', 'suggestedCategory', 'reviewer', 'article'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $needle = mb_substr($search, 0, 120);
                $query->where(function ($inner) use ($needle) {
                    $inner->where('title', 'like', "%{$needle}%")
                        ->orWhere('angle', 'like', "%{$needle}%")
                        ->orWhereHas('researchItem', fn ($item) => $item->where('title', 'like', "%{$needle}%"));
                });
            })
            ->orderByRaw("case when status = ? then 0 else 1 end", [ArticleBrief::STATUS_PENDING_REVIEW])
            ->latest();

        return view('admin.article-briefs.index', [
            'briefs' => $query->paginate(12)->withQueryString(),
            'categories' => Category::query()->where('type', 'article')->orderBy('name')->get(),
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'statusOptions' => [
                ArticleBrief::STATUS_PENDING_REVIEW,
                ArticleBrief::STATUS_APPROVED,
                ArticleBrief::STATUS_REJECTED,
                ArticleBrief::STATUS_DRAFTED,
            ],
            'counts' => [
                'pending' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_PENDING_REVIEW)->count(),
                'approved' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_APPROVED)->count(),
                'rejected' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_REJECTED)->count(),
                'drafted' => ArticleBrief::query()->where('status', ArticleBrief::STATUS_DRAFTED)->count(),
            ],
        ]);
    }

    public function update(Request $request, ArticleBrief $articleBrief): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated = $this->freshnessAdjustedScores($articleBrief, $validated);

        $articleBrief->update([
            ...$validated,
            'source_urls' => $this->linesToArray((string) ($validated['source_urls'] ?? '')),
            'suggested_tags' => $this->tagsToArray((string) ($validated['suggested_tags'] ?? '')),
        ]);

        return redirect()
            ->route('admin.article-briefs.index', $this->preservedFilters($request))
            ->with('status', 'Article brief updated.');
    }

    public function approve(Request $request, ArticleBrief $articleBrief, JimmyWritingService $jimmy): RedirectResponse
    {
        $articleBrief->loadMissing('researchItem');
        $freshness = $articleBrief->freshness();

        if (! $freshness['approvable']) {
            return redirect()
                ->route('admin.article-briefs.index', $this->preservedFilters($request))
                ->withErrors(['timeliness' => BriefFreshness::approvalMessage($freshness)]);
        }

        $articleBrief->update([
            'status' => ArticleBrief::STATUS_APPROVED,
            'rejection_reason' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $result = $jimmy->draftFromBrief($articleBrief->refresh(), $request->user());

        if (! ($result['ok'] ?? false)) {
            return redirect()
                ->route('admin.article-briefs.index', $this->preservedFilters($request))
                ->with('status', 'Article brief approved for Jimmy.')
                ->withErrors([
                    'jimmy' => 'Article brief approved, but Jimmy could not create a draft immediately: '.($result['message'] ?? 'Provider unavailable.'),
                ]);
        }

        if (($result['article'] ?? null) !== null) {
            $message = ($result['skipped'] ?? false)
                ? ($result['message'] ?? 'This brief already has an article draft.')
                : 'Article brief approved. Jimmy draft article created.';

            return redirect()
                ->route('admin.articles.edit', $result['article'])
                ->with('status', $message);
        }

        return redirect()
            ->route('admin.article-briefs.index', $this->preservedFilters($request))
            ->with('status', $result['message'] ?? 'Article brief approved for Jimmy.');
    }

    public function reject(Request $request, ArticleBrief $articleBrief): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $articleBrief->update([
            'status' => ArticleBrief::STATUS_REJECTED,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('admin.article-briefs.index', $this->preservedFilters($request))
            ->with('status', 'Article brief rejected.');
    }

    public function draft(Request $request, ArticleBrief $articleBrief, JimmyWritingService $jimmy): RedirectResponse
    {
        $result = $jimmy->draftFromBrief($articleBrief, $request->user());

        if (! ($result['ok'] ?? false)) {
            return redirect()
                ->route('admin.article-briefs.index', $this->preservedFilters($request))
                ->withErrors(['jimmy' => $result['message'] ?? 'Jimmy could not create a draft from this brief.']);
        }

        if (($result['article'] ?? null) !== null) {
            return redirect()
                ->route('admin.articles.edit', $result['article'])
                ->with('status', $result['message'] ?? 'Jimmy draft article created.');
        }

        return redirect()
            ->route('admin.article-briefs.index', $this->preservedFilters($request))
            ->with('status', $result['message'] ?? 'Jimmy draft complete.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'angle' => ['nullable', 'string'],
            'source_urls' => ['nullable', 'string'],
            'suggested_category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', 'article')],
            'suggested_tags' => ['nullable', 'string', 'max:2000'],
            'locality_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'newsworthiness_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'timeliness_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'confidence_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'duplicate_risk' => ['required', 'numeric', 'min:0', 'max:100'],
            'editorial_notes' => ['nullable', 'string'],
        ]);
    }

    private function freshnessAdjustedScores(ArticleBrief $articleBrief, array $validated): array
    {
        $articleBrief->loadMissing('researchItem');
        $freshness = $articleBrief->freshness();
        $modelTimelinessScore = array_key_exists('timeliness_score', $validated)
            ? (float) $validated['timeliness_score']
            : (float) $articleBrief->timeliness_score;

        $validated['timeliness_score'] = BriefFreshness::effectiveTimelinessScore($modelTimelinessScore, $freshness);
        $validated['newsworthiness_score'] = BriefFreshness::capNewsworthiness((float) $validated['newsworthiness_score'], $freshness);
        $validated['editorial_notes'] = BriefFreshness::appendNote((string) ($validated['editorial_notes'] ?? ''), $freshness);

        return $validated;
    }

    private function linesToArray(string $value): array
    {
        return collect(preg_split('/\R+/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function tagsToArray(string $value): array
    {
        return collect(preg_split('/[\n,]+/', $value) ?: [])
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function preservedFilters(Request $request): array
    {
        return collect($request->only(['status', 'q']))
            ->filter(fn ($value): bool => filled($value))
            ->all();
    }
}
