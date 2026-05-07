<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRevisionNote;
use App\Models\ArticleWordLedger;
use App\Models\Category;
use App\Models\LocationNode;
use App\Models\Setting;
use App\Models\Tag;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';

        $query = Article::query()
            ->with(['author', 'editor', 'categories', 'wordLedger'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $needle = mb_substr($search, 0, 120);
                $q->where(function ($inner) use ($needle) {
                    $inner->where('title', 'like', "%{$needle}%")
                        ->orWhere('slug', 'like', "%{$needle}%")
                        ->orWhereHas('author', fn ($a) => $a->where('name', 'like', "%{$needle}%")->orWhere('email', 'like', "%{$needle}%"));
                });
            });

        $query->orderBy(match ($sort) {
            'oldest' => 'created_at',
            default => 'created_at',
        }, $sort === 'oldest' ? 'asc' : 'desc');

        $articles = $query->paginate(15)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($articles);
        }

        return view('admin.articles.index', [
            'articles' => $articles,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'sort' => $sort,
            ],
            'statusOptions' => ['draft', 'pending_review', 'revision_requested', 'published'],
        ]);
    }

    public function show(Request $request, Article $article)
    {
        $article->load(['author', 'editor', 'categories', 'tags', 'locations', 'wordLedger', 'revisionNotes.author']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'article' => $article]);
        }

        return redirect()->route('admin.articles.edit', $article);
    }

    public function create(): View
    {
        return view('admin.articles.form', [
            'article' => new Article(),
            'categories' => Category::where('type', 'article')->orderBy('name')->get(),
            'tags' => Tag::where('type', 'article')->orderBy('name')->get(),
            'locations' => LocationNode::query()->orderBy('name')->get(),
            'selectedCategoryIds' => [],
            'selectedTagIds' => [],
            'selectedLocationIds' => [],
            'pageTitle' => 'Create Article',
            'formAction' => route('admin.articles.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? null);
        $data['submitted_at'] = $this->submittedAt($data['status'], null, $data['submitted_at'] ?? null);
        $data['editor_user_id'] = $data['status'] === 'published' ? $request->user()->id : null;
        $data = $this->handleUploads($request, $data);

        $article = Article::create($data);
        $article->categories()->sync($request->input('category_ids', []));
        $article->tags()->sync($request->input('tag_ids', []));
        $article->locations()->sync($request->input('location_ids', []));
        $this->syncRevisionNote($article, $request);
        $this->syncWordLedger($article, $request);
        $audit->log($request, 'article.created', $article, [], $article->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'article' => $article->fresh()->load(['author', 'editor', 'categories', 'tags', 'locations', 'wordLedger'])], 201);
        }

        return redirect()->route('admin.articles.edit', $article)->with('status', 'Article saved.');
    }

    public function edit(Article $article): View
    {
        $article->load(['categories', 'tags', 'locations', 'author', 'wordLedger', 'revisionNotes.author']);

        return view('admin.articles.form', [
            'article' => $article,
            'categories' => Category::where('type', 'article')->orderBy('name')->get(),
            'tags' => Tag::where('type', 'article')->orderBy('name')->get(),
            'locations' => LocationNode::query()->orderBy('name')->get(),
            'selectedCategoryIds' => $article->categories->modelKeys(),
            'selectedTagIds' => $article->tags->modelKeys(),
            'selectedLocationIds' => $article->locations->modelKeys(),
            'pageTitle' => 'Edit Article',
            'formAction' => route('admin.articles.update', $article),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, Article $article, AuditLogService $audit)
    {
        $before = $article->toArray();
        $data = $this->validated($request, $article);
        $data['published_at'] = $this->publishedAt($data['status'], $data['published_at'] ?? $article->published_at);
        $data['submitted_at'] = $this->submittedAt($data['status'], $article->submitted_at, $data['submitted_at'] ?? null);
        $data['editor_user_id'] = $data['status'] === 'published' ? $request->user()->id : $article->editor_user_id;
        $data = $this->handleUploads($request, $data, $article);

        $article->update($data);
        $article->categories()->sync($request->input('category_ids', []));
        $article->tags()->sync($request->input('tag_ids', []));
        $article->locations()->sync($request->input('location_ids', []));
        $this->syncRevisionNote($article, $request);
        $this->syncWordLedger($article->fresh(), $request);
        $audit->log($request, 'article.updated', $article, $before, $article->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'article' => $article->fresh()->load(['author', 'editor', 'categories', 'tags', 'locations', 'wordLedger'])]);
        }

        return redirect()->route('admin.articles.edit', $article)->with('status', 'Article updated.');
    }

    public function destroy(Request $request, Article $article, AuditLogService $audit)
    {
        $before = $article->toArray();
        $audit->log($request, 'article.deleted', $article, $before, []);
        $this->deleteFile($article->featured_image);
        $article->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.articles.index')->with('status', 'Article deleted.');
    }

    public function bulk(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['set_status', 'delete'])],
            'status' => ['nullable', Rule::in(['draft', 'pending_review', 'revision_requested', 'published'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
        ]);

        $ids = collect($validated['ids'])->filter()->unique()->values()->all();
        $targets = Article::query()->whereIn('slug', $ids)->get();

        foreach ($targets as $article) {
            $before = $article->toArray();

            if ($validated['action'] === 'delete') {
                $audit->log($request, 'article.bulk_delete', $article, $before, []);
                $this->deleteFile($article->featured_image);
                $article->delete();
                continue;
            }

            $status = (string) ($validated['status'] ?? '');
            abort_unless($status !== '', 422, 'Missing status.');

            $next = [
                'status' => $status,
                'published_at' => $this->publishedAt($status, $article->published_at),
                'submitted_at' => $this->submittedAt($status, $article->submitted_at, $article->submitted_at),
            ];
            if ($status === 'published' && ! $article->editor_user_id) {
                $next['editor_user_id'] = $request->user()->id;
            }
            if ($status !== 'published') {
                $next['editor_user_id'] = $article->editor_user_id;
            }

            $article->update($next);
            $this->syncWordLedger($article->fresh(), $request);
            $audit->log($request, 'article.bulk_set_status', $article, $before, $article->fresh()->toArray());
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'affected' => $targets->count()]);
        }

        return redirect()->route('admin.articles.index')->with('status', 'Bulk operation completed.');
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($article?->id)],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'revision_note' => ['nullable', 'string'],
            'submitted_at' => ['nullable', 'date'],
            'featured_image_upload' => ['nullable', 'image', 'max:5120'],
            'remove_featured_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'pending_review', 'revision_requested', 'published'])],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['integer', 'exists:location_nodes,id'],
        ]);
    }

    private function publishedAt(string $status, mixed $publishedAt): mixed
    {
        if ($status !== 'published') {
            return null;
        }

        return $publishedAt ?: now();
    }

    private function submittedAt(string $status, mixed $existingSubmittedAt, mixed $submittedAt): mixed
    {
        if (! in_array($status, ['pending_review', 'revision_requested', 'published'], true)) {
            return null;
        }

        return $existingSubmittedAt ?: $submittedAt ?: now();
    }

    private function handleUploads(Request $request, array $data, ?Article $article = null): array
    {
        if ($request->boolean('remove_featured_image') && $article?->featured_image) {
            $this->deleteFile($article->featured_image);
            $data['featured_image'] = null;
        } elseif ($request->hasFile('featured_image_upload')) {
            $this->deleteFile($article?->featured_image);
            $data['featured_image'] = $this->storeImage($request->file('featured_image_upload'), 'articles/featured');
        }

        return $data;
    }

    private function storeImage(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function syncWordLedger(Article $article, Request $request): void
    {
        if ($article->status !== 'published') {
            return;
        }

        $ratePerWord = (float) Setting::getValue('writer.per_word_rate', 0);
        $wordCount = $article->wordCount();

        ArticleWordLedger::updateOrCreate(
            ['article_id' => $article->id],
            [
                'writer_user_id' => $article->user_id,
                'approved_by_user_id' => $request->user()->id,
                'word_count' => $wordCount,
                'rate_per_word' => $ratePerWord,
                'gross_amount' => round($wordCount * $ratePerWord, 2),
                'status' => 'pending',
                'approved_at' => now(),
            ]
        );
    }

    private function syncRevisionNote(Article $article, Request $request): void
    {
        $note = trim((string) $request->input('revision_note'));

        if ($note === '') {
            return;
        }

        ArticleRevisionNote::create([
            'article_id' => $article->id,
            'author_user_id' => $request->user()->id,
            'status' => $article->status,
            'note' => $note,
        ]);
    }
}
