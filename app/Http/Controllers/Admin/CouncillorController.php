<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Councillor;
use App\Models\CivicFaultReport;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CouncillorController extends Controller
{
    public function index(): View
    {
        return view('admin.councillors.index', [
            'councillors' => Councillor::withCount('assignedFaultReports')->orderBy('full_name')->paginate(20),
        ]);
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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $councillor = Councillor::create($data);
        $this->ensureCouncillorRole($councillor);
        $this->syncArea($councillor, $request);

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

    public function update(Request $request, Councillor $councillor): RedirectResponse
    {
        $data = $this->validated($request, $councillor);
        $councillor->update($data);
        $this->ensureCouncillorRole($councillor);
        $this->syncArea($councillor, $request);

        return redirect()->route('admin.councillors.edit', $councillor)->with('status', 'Councillor updated.');
    }

    public function destroy(Councillor $councillor): RedirectResponse
    {
        $councillor->delete();

        return redirect()->route('admin.councillors.index')->with('status', 'Councillor removed.');
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

    private function syncArea(Councillor $councillor, Request $request): void
    {
        $name = $request->string('area_name')->toString();
        $geojsonRaw = $request->string('area_geojson')->toString();

        if (! $name || ! $geojsonRaw) {
            return;
        }

        $decoded = json_decode($geojsonRaw, true);
        if (! is_array($decoded)) {
            return;
        }

        $areaId = $request->integer('area_id');

        if ($areaId) {
            $area = $councillor->areas()->whereKey($areaId)->first();
            if ($area) {
                $area->update([
                    'name' => $name,
                    'geojson' => $decoded,
                    'is_active' => $request->boolean('area_is_active'),
                ]);
                return;
            }
        }

        $councillor->areas()->create([
            'name' => $name,
            'geojson' => $decoded,
            'is_active' => $request->boolean('area_is_active'),
        ]);
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
