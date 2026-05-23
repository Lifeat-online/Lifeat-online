<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Voucher;
use App\Services\AiContentAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AiAssistController extends Controller
{
    public function listingDescription(Request $request, AiContentAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'listing_id' => ['nullable', 'integer', 'exists:listings,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'rough_notes' => ['nullable', 'string', 'max:5000'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:12000'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $listing = filled($validated['listing_id'] ?? null)
            ? Listing::query()->find((int) $validated['listing_id'])
            : null;

        $categories = Category::query()
            ->whereIn('id', $validated['category_ids'] ?? [])
            ->pluck('name')
            ->all();

        $result = $assistant->generateListingDescription([
            ...$validated,
            'categories' => $categories,
        ], $listing, $request->user());

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? 'AI request completed.',
            'suggestion' => $result['payload'] ?? null,
            'generation_id' => ($result['generation'] ?? null)?->id,
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function articleSeo(Request $request, AiContentAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:20000'],
            'source_locale' => ['nullable', 'string', 'max:10'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:120'],
            'locations' => ['nullable', 'array'],
            'locations.*' => ['nullable', 'string', 'max:120'],
        ]);

        $result = $assistant->generateArticleSeo($validated, $request->user());
        $suggestion = (array) ($result['payload'] ?? []);

        if (isset($suggestion['suggested_slug'])) {
            $suggestion['suggested_slug'] = Str::slug((string) $suggestion['suggested_slug']);
        }

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? 'AI request completed.',
            'suggestion' => $suggestion ?: null,
            'generation_id' => ($result['generation'] ?? null)?->id,
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function articleTranslation(Request $request, Article $article, AiContentAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'target_locale' => ['required', 'string', Rule::in(array_keys((array) config('localization.supported')))],
            'force' => ['nullable', 'boolean'],
        ]);

        $result = $assistant->translateArticle(
            $article,
            $validated['target_locale'],
            $request->user(),
            $request->boolean('force')
        );

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? 'AI translation request completed.',
            'translation' => isset($result['translation']) ? [
                'locale' => $result['translation']->locale,
                'content' => $result['translation']->content,
                'provider' => $result['translation']->provider,
                'model' => $result['translation']->model,
                'translated_at' => $result['translation']->translated_at?->toIso8601String(),
            ] : null,
            'generation_id' => ($result['generation'] ?? null)?->id,
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function eventDescription(Request $request, AiContentAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'listing_id' => ['nullable', 'integer', 'exists:listings,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:12000'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'start_at' => ['nullable', 'string', 'max:80'],
            'end_at' => ['nullable', 'string', 'max:80'],
            'website_url' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'max:80'],
            'is_all_day' => ['nullable', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'rough_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $event = filled($validated['event_id'] ?? null)
            ? Event::query()->with('listing.categories')->find((int) $validated['event_id'])
            : null;
        $listing = filled($validated['listing_id'] ?? null)
            ? Listing::query()->with('categories')->findOrFail((int) $validated['listing_id'])
            : $event?->listing;

        $categories = Category::query()
            ->whereIn('id', $validated['category_ids'] ?? [])
            ->pluck('name')
            ->all();

        $result = $assistant->generateEventDescription([
            ...$validated,
            'categories' => $categories,
        ], $listing, $event, $request->user());

        return $this->suggestionResponse($this->normalizeEventSuggestion($result));
    }

    public function adCopy(Request $request, AiContentAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'placement' => ['nullable', 'string', 'max:80'],
            'destination_url' => ['nullable', 'string', 'max:500'],
            'rough_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $listing = Listing::query()->with('categories')->findOrFail((int) $validated['listing_id']);
        $event = filled($validated['event_id'] ?? null) ? Event::query()->find((int) $validated['event_id']) : null;
        $result = $assistant->generateAdCopy($validated, $listing, $event, $request->user());

        return $this->suggestionResponse($result);
    }

    public function pushCopy(Request $request, AiContentAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'audience_scope' => ['nullable', 'string', 'max:80'],
            'target_city' => ['nullable', 'string', 'max:255'],
            'target_region' => ['nullable', 'string', 'max:255'],
            'rough_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $listing = Listing::query()->with('categories')->findOrFail((int) $validated['listing_id']);
        $event = filled($validated['event_id'] ?? null) ? Event::query()->find((int) $validated['event_id']) : null;
        $result = $assistant->generatePushCopy($validated, $listing, $event, $request->user());

        return $this->suggestionResponse($result);
    }

    public function voucherCopy(Request $request, AiContentAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'voucher_type' => ['nullable', 'string', 'max:80'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['nullable', 'string', 'max:8'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'start_at' => ['nullable', 'string', 'max:80'],
            'end_at' => ['nullable', 'string', 'max:80'],
            'rough_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $listing = Listing::query()->with('categories')->findOrFail((int) $validated['listing_id']);
        $voucher = filled($validated['voucher_id'] ?? null) ? Voucher::query()->find((int) $validated['voucher_id']) : null;
        $result = $assistant->generateVoucherCopy($validated, $listing, $voucher, $request->user());

        return $this->suggestionResponse($result);
    }

    private function suggestionResponse(array $result): JsonResponse
    {
        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? 'AI request completed.',
            'suggestion' => $result['payload'] ?? null,
            'generation_id' => ($result['generation'] ?? null)?->id,
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    private function normalizeEventSuggestion(array $result): array
    {
        $payload = (array) ($result['payload'] ?? []);

        if (isset($payload['suggested_slug'])) {
            $payload['suggested_slug'] = Str::slug((string) $payload['suggested_slug']);
        }

        if ($payload !== []) {
            $result['payload'] = $payload;
        }

        return $result;
    }
}
