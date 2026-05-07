<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\MarketingIntegration;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingIntegrationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();
        $search = trim((string) $request->string('q'));
        $sort = $request->string('sort')->toString() ?: 'newest';

        $query = MarketingIntegration::query()
            ->with(['listing', 'updatedBy'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($search !== '', function ($q) use ($search) {
                $needle = mb_substr($search, 0, 120);
                $q->where(function ($inner) use ($needle) {
                    $inner->where('type', 'like', "%{$needle}%")
                        ->orWhere('provider', 'like', "%{$needle}%")
                        ->orWhereHas('listing', fn ($l) => $l->where('title', 'like', "%{$needle}%"));
                });
            });

        $query->orderBy(match ($sort) {
            'oldest' => 'created_at',
            default => 'created_at',
        }, $sort === 'oldest' ? 'asc' : 'desc');

        $integrations = $query->paginate(20)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'integrations' => $integrations]);
        }

        return view('admin.integrations.index', [
            'integrations' => $integrations,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'type' => $type,
                'sort' => $sort,
            ],
            'typeOptions' => MarketingIntegration::query()->select('type')->distinct()->orderBy('type')->pluck('type')->all(),
        ]);
    }

    public function show(Request $request, MarketingIntegration $integration)
    {
        $integration->load(['listing', 'createdBy', 'updatedBy']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'integration' => $integration]);
        }

        return redirect()->route('admin.integrations.edit', $integration);
    }

    public function create(): View
    {
        return view('admin.integrations.form', [
            'integration' => new MarketingIntegration(),
            'listings' => Listing::query()->orderBy('title')->limit(200)->get(['id', 'title']),
            'pageTitle' => 'Create Integration',
            'formAction' => route('admin.integrations.store'),
            'formMethod' => 'POST',
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $data = $this->validated($request);

        $integration = MarketingIntegration::create([
            ...$data,
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        $audit->log($request, 'marketing_integration.created', $integration, [], $integration->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'integration' => $integration->fresh()->load('listing')], 201);
        }

        return redirect()->route('admin.integrations.edit', $integration)->with('status', 'Integration saved.');
    }

    public function edit(MarketingIntegration $integration): View
    {
        return view('admin.integrations.form', [
            'integration' => $integration->load(['listing']),
            'listings' => Listing::query()->orderBy('title')->limit(200)->get(['id', 'title']),
            'pageTitle' => 'Edit Integration',
            'formAction' => route('admin.integrations.update', $integration),
            'formMethod' => 'PUT',
        ]);
    }

    public function update(Request $request, MarketingIntegration $integration, AuditLogService $audit)
    {
        $before = $integration->toArray();
        $data = $this->validated($request, $integration);

        $integration->update([
            ...$data,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        $audit->log($request, 'marketing_integration.updated', $integration, $before, $integration->fresh()->toArray());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'integration' => $integration->fresh()->load('listing')]);
        }

        return redirect()->route('admin.integrations.edit', $integration)->with('status', 'Integration updated.');
    }

    public function destroy(Request $request, MarketingIntegration $integration, AuditLogService $audit)
    {
        $before = $integration->toArray();
        $audit->log($request, 'marketing_integration.deleted', $integration, $before, []);
        $integration->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.integrations.index')->with('status', 'Integration deleted.');
    }

    public function bulk(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:marketing_integrations,id'],
        ]);

        $targets = MarketingIntegration::query()->whereIn('id', $validated['ids'])->get();

        foreach ($targets as $integration) {
            $before = $integration->toArray();

            match ($validated['action']) {
                'activate' => $integration->update(['status' => 'active']),
                'deactivate' => $integration->update(['status' => 'inactive']),
                'delete' => $integration->delete(),
            };

            $audit->log($request, 'marketing_integration.bulk_'.$validated['action'], $integration, $before, $integration->fresh()?->toArray() ?? []);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'affected' => $targets->count()]);
        }

        return redirect()->route('admin.integrations.index')->with('status', 'Bulk operation completed.');
    }

    private function validated(Request $request, ?MarketingIntegration $integration = null): array
    {
        $data = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'type' => ['required', 'string', 'max:255', Rule::unique('marketing_integrations', 'type')->where(fn ($q) => $q->where('listing_id', (int) $request->input('listing_id')))->ignore($integration?->id)],
            'provider' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['inactive', 'active'])],
            'settings_text' => ['nullable', 'string'],
        ]);

        $settingsText = trim((string) ($data['settings_text'] ?? ''));
        unset($data['settings_text']);

        if ($settingsText !== '') {
            $decoded = json_decode($settingsText, true);
            abort_unless(is_array($decoded), 422, 'settings_text must be valid JSON object/array.');
            $data['settings_json'] = $decoded;
        } else {
            $data['settings_json'] = null;
        }

        return $data;
    }
}
