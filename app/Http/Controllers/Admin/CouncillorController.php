<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Councillor;
use App\Models\CivicFaultReport;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouncillorController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->string('q')),
            'active' => $request->string('active')->toString(),
        ];

        $councillors = Councillor::query()
            ->withCount('assignedFaultReports')
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $needle = mb_substr($filters['q'], 0, 120);
                $query->where(function ($inner) use ($needle) {
                    $inner->where('full_name', 'like', "%{$needle}%")
                        ->orWhere('email', 'like', "%{$needle}%")
                        ->orWhere('phone', 'like', "%{$needle}%");
                });
            })
            ->when($filters['active'] !== '', fn ($query) => $query->where('is_active', $filters['active'] === 'yes'))
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'councillors' => $councillors]);
        }

        return view('admin.councillors.index', [
            'councillors' => $councillors,
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Councillor $councillor)
    {
        $councillor->load(['areas', 'user']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'councillor' => $councillor]);
        }

        return redirect()->route('admin.councillors.edit', $councillor);
    }

    public function create(): View
    {
        return view('admin.councillors.form', [
            'councillor' => new Councillor(),
            'areasJson' => [],
            'users' => User::orderBy('name')->limit(200)->get(),
            'categories' => CivicFaultReport::categories(),
            'formAction' => route('admin.councillors.store'),
            'formMethod' => 'POST',
            'pageTitle' => 'Add Councillor',
        ]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $data = $this->validated($request);
        $councillor = Councillor::create($data);
        $this->ensureCouncillorRole($councillor);
        $areaAudit = $this->syncArea($councillor, $request);
        $audit->log($request, 'councillor.created', $councillor, [], $councillor->toArray());
        if ($areaAudit) {
            $audit->log($request, $areaAudit['action'], $areaAudit['subject'], $areaAudit['before'], $areaAudit['after']);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'councillor' => $councillor->fresh()->load('areas')], 201);
        }

        return redirect()->route('admin.councillors.edit', $councillor)->with('status', 'Councillor created.');
    }

    public function edit(Councillor $councillor): View
    {
        $councillor->load('areas');

        return view('admin.councillors.form', [
            'councillor' => $councillor,
            'areasJson' => $councillor->areas
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'geojson' => $a->geojson,
                    'is_active' => $a->is_active,
                ])
                ->values()
                ->all(),
            'users' => User::orderBy('name')->limit(200)->get(),
            'categories' => CivicFaultReport::categories(),
            'formAction' => route('admin.councillors.update', $councillor),
            'formMethod' => 'PUT',
            'pageTitle' => 'Edit Councillor',
        ]);
    }

    public function update(Request $request, Councillor $councillor, AuditLogService $audit)
    {
        $before = $councillor->toArray();
        $data = $this->validated($request, $councillor);
        $councillor->update($data);
        $this->ensureCouncillorRole($councillor);
        $areaAudit = $this->syncArea($councillor, $request);
        $audit->log($request, 'councillor.updated', $councillor, $before, $councillor->fresh()->toArray());
        if ($areaAudit) {
            $audit->log($request, $areaAudit['action'], $areaAudit['subject'], $areaAudit['before'], $areaAudit['after']);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'councillor' => $councillor->fresh()->load('areas')]);
        }

        return redirect()->route('admin.councillors.edit', $councillor)->with('status', 'Councillor updated.');
    }

    public function destroy(Request $request, Councillor $councillor, AuditLogService $audit)
    {
        $before = $councillor->toArray();
        $councillor->delete();
        $audit->log($request, 'councillor.deleted', $councillor, $before, []);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('admin.councillors.index')->with('status', 'Councillor removed.');
    }

    public function bulk(Request $request, AuditLogService $audit)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:councillors,id'],
        ]);

        $targets = Councillor::query()->whereIn('id', $validated['ids'])->get();

        foreach ($targets as $councillor) {
            $before = $councillor->toArray();

            match ($validated['action']) {
                'activate' => $councillor->update(['is_active' => true]),
                'deactivate' => $councillor->update(['is_active' => false]),
                'delete' => $councillor->delete(),
            };

            $audit->log($request, 'councillor.bulk_'.$validated['action'], $councillor, $before, $councillor->fresh()?->toArray() ?? []);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'affected' => $targets->count()]);
        }

        return redirect()->route('admin.councillors.index')->with('status', 'Bulk operation completed.');
    }

    private function validated(Request $request, ?Councillor $councillor = null): array
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'office_address' => ['nullable', 'string', 'max:255'],
            'portfolios' => ['nullable', 'array'],
            'portfolios.*' => ['string', 'max:60'],
            'category_responsibilities' => ['nullable', 'array'],
            'category_responsibilities.*' => ['string', Rule::in(array_keys(CivicFaultReport::categories()))],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['portfolios'] = array_values(array_filter($data['portfolios'] ?? [], fn ($v) => is_string($v) && trim($v) !== ''));
        $data['category_responsibilities'] = array_values(array_filter($data['category_responsibilities'] ?? [], fn ($v) => is_string($v) && trim($v) !== ''));

        return $data;
    }

    private function syncArea(Councillor $councillor, Request $request): ?array
    {
        $name = $request->string('area_name')->toString();
        $geojsonRaw = $request->string('area_geojson')->toString();

        if (! $name || ! $geojsonRaw) {
            return null;
        }

        $decoded = json_decode($geojsonRaw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $areaId = $request->integer('area_id');

        if ($areaId) {
            $area = $councillor->areas()->whereKey($areaId)->first();
            if ($area) {
                $before = $area->toArray();
                $area->update([
                    'name' => $name,
                    'geojson' => $decoded,
                    'is_active' => $request->boolean('area_is_active'),
                ]);
                return [
                    'action' => 'councillor_area.updated',
                    'subject' => $area,
                    'before' => $before,
                    'after' => $area->fresh()->toArray(),
                ];
            }
        }

        $area = $councillor->areas()->create([
            'name' => $name,
            'geojson' => $decoded,
            'is_active' => $request->boolean('area_is_active'),
        ]);
        return [
            'action' => 'councillor_area.created',
            'subject' => $area,
            'before' => [],
            'after' => $area->toArray(),
        ];
    }

    private function ensureCouncillorRole(Councillor $councillor): void
    {
        $user = $councillor->user;
        if (! $user) {
            return;
        }

        if ($user->hasRole('admin', 'editor', 'staff', 'support', 'writer')) {
            return;
        }

        if ($user->hasRole('councillor')) {
            return;
        }

        $user->forceFill(['role' => 'councillor'])->save();
    }
}
