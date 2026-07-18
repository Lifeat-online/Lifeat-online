<?php

namespace App\Ai\Editorial;

use App\Models\ClaimEvidence;
use App\Models\EditorialClaim;
use App\Models\EditorialDossier;
use App\Models\ResearchItem;
use App\Models\StoryCluster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DossierBuilder
{
    public function build(ResearchItem $item): EditorialDossier
    {
        $snapshot = $item->snapshots()->latest('fetched_at')->first();
        if (! $snapshot) {
            throw new \RuntimeException('A durable source snapshot is required before building a dossier.');
        }

        return DB::transaction(function () use ($item, $snapshot): EditorialDossier {
            $fingerprint = hash('sha256', Str::lower(Str::squish(preg_replace('/[^\pL\pN\s]/u', ' ', $item->title) ?? $item->title)));
            $cluster = StoryCluster::query()->firstOrCreate(['fingerprint' => $fingerprint], [
                'title' => $item->title,
                'status' => 'open',
            ]);
            $cluster->researchItems()->syncWithoutDetaching([$item->id]);

            $dossier = EditorialDossier::query()->firstOrCreate(['story_cluster_id' => $cluster->id], [
                'title' => $item->title,
                'summary' => $item->summary,
                'status' => 'draft',
            ]);
            $claim = EditorialClaim::query()->firstOrCreate([
                'editorial_dossier_id' => $dossier->id,
                'claim' => $item->title,
            ], ['importance' => 'high', 'status' => 'supported']);
            ClaimEvidence::query()->firstOrCreate([
                'editorial_claim_id' => $claim->id,
                'source_snapshot_id' => $snapshot->id,
                'stance' => 'supports',
            ], [
                'excerpt' => Str::limit($snapshot->content, 1200, ''),
                'authority_score' => (int) data_get($item->researchSource?->metadata, 'trust_score', 50),
            ]);

            return $dossier->fresh(['cluster.researchItems', 'claims.evidence.snapshot']);
        });
    }
}
