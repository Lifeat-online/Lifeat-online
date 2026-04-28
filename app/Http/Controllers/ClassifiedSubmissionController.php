<?php

namespace App\Http\Controllers;

use App\Models\Classified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClassifiedSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        return view('classifieds.manage.index', [
            'classifieds' => Classified::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('classifieds.manage.form', [
            'classified' => new Classified(),
            'pageTitle' => 'Post Classified',
            'formAction' => route('classifieds.manage.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['status'] = Classified::STATUS_PENDING;
        $data['submitted_at'] = now();
        $data['reviewed_by_user_id'] = null;
        $data['reviewed_at'] = null;
        $data['moderation_notes'] = null;
        $data['published_at'] = null;
        $data['featured_image'] = $request->file('featured_image')?->store('classifieds', 'public');

        $classified = Classified::create($data);

        return redirect()
            ->route('classifieds.manage.edit', $classified)
            ->with('status', 'Classified submitted for moderation.');
    }

    public function edit(Request $request, Classified $classified): View
    {
        abort_unless($classified->user_id === $request->user()->id, 403);
        abort_if($classified->status === Classified::STATUS_PUBLISHED, 403);

        return view('classifieds.manage.form', [
            'classified' => $classified,
            'pageTitle' => 'Edit Classified',
            'formAction' => route('classifieds.manage.update', $classified),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, Classified $classified): RedirectResponse
    {
        abort_unless($classified->user_id === $request->user()->id, 403);
        abort_if($classified->status === Classified::STATUS_PUBLISHED, 403);

        $data = $this->validated($request, $classified);
        $data['slug'] = $this->uniqueSlug($data['title'], $classified);
        $data['status'] = Classified::STATUS_PENDING;
        $data['submitted_at'] = now();
        $data['reviewed_by_user_id'] = null;
        $data['reviewed_at'] = null;
        $data['moderation_notes'] = null;
        $data['published_at'] = null;

        if ($request->hasFile('featured_image')) {
            if ($classified->featured_image) {
                Storage::disk('public')->delete($classified->featured_image);
            }

            $data['featured_image'] = $request->file('featured_image')->store('classifieds', 'public');
        }

        $classified->update($data);

        return redirect()
            ->route('classifieds.manage.edit', $classified)
            ->with('status', 'Classified updated and returned to moderation.');
    }

    private function validated(Request $request, ?Classified $classified = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'contact_for_price' => ['nullable', 'boolean'],
            'featured_image' => ['nullable', 'image', 'max:2048'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => [Rule::in(Classified::moderationStatuses())],
        ]);
    }

    private function uniqueSlug(string $title, ?Classified $classified = null): string
    {
        $base = Str::slug($title);
        $slug = $base !== '' ? $base : 'classified';
        $suffix = 1;

        while (
            Classified::query()
                ->where('slug', $slug)
                ->when($classified, fn ($query) => $query->whereKeyNot($classified->id))
                ->exists()
        ) {
            $slug = ($base !== '' ? $base : 'classified').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
