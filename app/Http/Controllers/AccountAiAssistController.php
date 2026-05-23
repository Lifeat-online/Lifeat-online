<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Voucher;
use App\Services\AiContentAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AccountAiAssistController extends Controller
{
    public function eventDescription(Request $request, Listing $listing, AiContentAssistantService $assistant): JsonResponse
    {
        Gate::authorize('manage', $listing);

        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'title' => ['nullable', 'string', 'max:255'],
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
            ? Event::query()->where('listing_id', $listing->id)->find((int) $validated['event_id'])
            : null;
        $categories = Category::query()
            ->whereIn('id', $validated['category_ids'] ?? [])
            ->pluck('name')
            ->all();

        $result = $assistant->generateEventDescription([
            ...$validated,
            'categories' => $categories,
        ], $listing->loadMissing('categories'), $event, $request->user());

        return $this->suggestionResponse($this->normalizeEventSuggestion($result));
    }

    public function pushCopy(Request $request, Listing $listing, AiContentAssistantService $assistant): JsonResponse
    {
        Gate::authorize('manage', $listing);

        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'audience_scope' => ['nullable', 'string', 'max:80'],
            'target_city' => ['nullable', 'string', 'max:255'],
            'target_region' => ['nullable', 'string', 'max:255'],
            'rough_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $event = filled($validated['event_id'] ?? null)
            ? Event::query()->where('listing_id', $listing->id)->find((int) $validated['event_id'])
            : null;

        $result = $assistant->generatePushCopy($validated, $listing->loadMissing('categories'), $event, $request->user());

        return $this->suggestionResponse($result);
    }

    public function voucherCopy(Request $request, Listing $listing, AiContentAssistantService $assistant): JsonResponse
    {
        Gate::authorize('manage', $listing);

        $validated = $request->validate([
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

        $voucher = filled($validated['voucher_id'] ?? null)
            ? Voucher::query()->where('listing_id', $listing->id)->find((int) $validated['voucher_id'])
            : null;

        $result = $assistant->generateVoucherCopy($validated, $listing->loadMissing('categories'), $voucher, $request->user());

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
