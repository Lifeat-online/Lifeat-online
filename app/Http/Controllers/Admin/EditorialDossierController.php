<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClaimEvidence;
use App\Models\EditorialClaim;
use App\Models\EditorialDossier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EditorialDossierController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $dossiers = EditorialDossier::query()
            ->withCount('claims')
            ->with('cluster')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.editorial-dossiers.index', compact('dossiers', 'status'));
    }

    public function show(EditorialDossier $editorialDossier): View
    {
        $editorialDossier->load([
            'claims.evidence.snapshot.researchItem',
            'cluster.researchItems.snapshots',
        ]);
        $snapshots = $editorialDossier->cluster->researchItems
            ->flatMap->snapshots
            ->sortByDesc('fetched_at')
            ->values();

        return view('admin.editorial-dossiers.show', ['dossier' => $editorialDossier, 'snapshots' => $snapshots]);
    }

    public function approve(Request $request, EditorialDossier $editorialDossier): RedirectResponse
    {
        if (! $editorialDossier->hasSupportedHighImportanceClaims()) {
            return back()->withErrors(['dossier' => 'Every high-importance claim needs supporting evidence before approval.']);
        }

        $editorialDossier->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'Dossier approved for evidence-grounded writing.');
    }

    public function updateClaim(Request $request, EditorialDossier $editorialDossier, EditorialClaim $claim): RedirectResponse
    {
        abort_unless($claim->editorial_dossier_id === $editorialDossier->id, 404);
        $validated = $request->validate([
            'importance' => ['required', Rule::in(['low', 'medium', 'high'])],
            'status' => ['required', Rule::in(['unverified', 'verified', 'disputed', 'rejected'])],
        ]);
        $claim->update($validated);
        $this->returnToDraft($editorialDossier);

        return back()->with('status', 'Claim fact-check status updated.');
    }

    public function storeEvidence(Request $request, EditorialDossier $editorialDossier, EditorialClaim $claim): RedirectResponse
    {
        abort_unless($claim->editorial_dossier_id === $editorialDossier->id, 404);
        $validated = $request->validate([
            'source_snapshot_id' => ['required', 'integer', Rule::exists('source_snapshots', 'id')],
            'stance' => ['required', Rule::in(['supports', 'challenges', 'context'])],
            'excerpt' => ['nullable', 'string', 'max:4000'],
            'authority_score' => ['required', 'integer', 'min:0', 'max:100'],
        ]);
        $allowedSnapshotIds = $editorialDossier->cluster->researchItems()->with('snapshots:id,research_item_id')->get()
            ->flatMap->snapshots->pluck('id');
        abort_unless($allowedSnapshotIds->contains((int) $validated['source_snapshot_id']), 422, 'Evidence must come from this dossier cluster.');

        ClaimEvidence::query()->updateOrCreate([
            'editorial_claim_id' => $claim->id,
            'source_snapshot_id' => $validated['source_snapshot_id'],
            'stance' => $validated['stance'],
        ], $validated);
        $this->returnToDraft($editorialDossier);

        return back()->with('status', 'Evidence attached to the claim.');
    }

    private function returnToDraft(EditorialDossier $dossier): void
    {
        if ($dossier->status === 'approved') {
            $dossier->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
        }
    }
}
