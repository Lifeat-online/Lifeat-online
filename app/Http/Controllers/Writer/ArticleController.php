<?php

namespace App\Http\Controllers\Writer;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\LocationNode;
use App\Models\Tag;
use App\Support\Onboarding\WriterOnboardingChecklist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function index(Request $request, WriterOnboardingChecklist $onboarding): View
    {
        return view('writer.articles.index', [
            'articles' => Article::with(['categories', 'wordLedger', 'revisionNotes.author'])
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(15),
            'writerOnboarding' => $onboarding->forUser($request->user()),
        ]);
    }

    public function create(Request $request, WriterOnboardingChecklist $onboarding): View
    {
        return view('writer.articles.form', [
            'article' => new Article(),
            'categories' => Category::where('type', 'article')->orderBy('name')->get(),
            'tags' => Tag::where('type', 'article')->orderBy('name')->get(),
            'locations' => LocationNode::query()->orderBy('name')->get(),
            'selectedCategoryIds' => [],
            'selectedTagIds' => [],
            'selectedLocationIds' => [],
            'pageTitle' => 'Submit Article',
            'formAction' => route('writer.articles.store'),
            'formMethod' => 'POST',
            'writerOnboarding' => $onboarding->forUser($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['source_locale'] = $data['source_locale'] ?? app()->getLocale();
        $data['status'] = $request->boolean('submit_for_review') ? 'pending_review' : 'draft';
        $data['submitted_at'] = $data['status'] === 'pending_review' ? now() : null;
        $data['published_at'] = null;

        $article = Article::create($data);
        $article->categories()->sync($request->input('category_ids', []));
        $article->tags()->sync($request->input('tag_ids', []));
        $article->locations()->sync($request->input('location_ids', []));

        return redirect()->route('writer.articles.edit', $article)->with('status', 'Article saved.');
    }

    public function edit(Request $request, Article $article, WriterOnboardingChecklist $onboarding): View
    {
        abort_unless($article->user_id === $request->user()->id, 403);

        return view('writer.articles.form', [
            'article' => $article->load(['categories', 'tags', 'locations', 'revisionNotes.author']),
            'categories' => Category::where('type', 'article')->orderBy('name')->get(),
            'tags' => Tag::where('type', 'article')->orderBy('name')->get(),
            'locations' => LocationNode::query()->orderBy('name')->get(),
            'selectedCategoryIds' => $article->categories->modelKeys(),
            'selectedTagIds' => $article->tags->modelKeys(),
            'selectedLocationIds' => $article->locations->modelKeys(),
            'pageTitle' => 'Edit Submission',
            'formAction' => route('writer.articles.update', $article),
            'formMethod' => 'PUT',
            'writerOnboarding' => $onboarding->forUser($request->user()),
        ]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        abort_unless($article->user_id === $request->user()->id, 403);
        abort_if($article->status === 'published', 403);

        $data = $this->validated($request, $article);
        $data['status'] = $request->boolean('submit_for_review') ? 'pending_review' : 'draft';
        $data['submitted_at'] = $data['status'] === 'pending_review' ? now() : null;
        $data['published_at'] = null;

        $article->update($data);
        $article->categories()->sync($request->input('category_ids', []));
        $article->tags()->sync($request->input('tag_ids', []));
        $article->locations()->sync($request->input('location_ids', []));

        return redirect()->route('writer.articles.edit', $article)->with('status', 'Submission updated.');
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($article?->id)],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'source_locale' => ['nullable', Rule::in(array_keys((array) config('localization.supported')))],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'location_ids' => ['nullable', 'array'],
            'location_ids.*' => ['integer', 'exists:location_nodes,id'],
        ]);
    }
}
