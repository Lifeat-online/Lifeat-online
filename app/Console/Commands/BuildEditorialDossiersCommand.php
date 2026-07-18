<?php

namespace App\Console\Commands;

use App\Ai\Editorial\DossierBuilder;
use App\Models\ResearchItem;
use Illuminate\Console\Command;

class BuildEditorialDossiersCommand extends Command
{
    protected $signature = 'life:editorial:dossier {--item=* : Research item IDs} {--limit=20}';

    protected $description = 'Cluster snapshotted research and build durable claim/evidence dossiers.';

    public function handle(DossierBuilder $builder): int
    {
        $items = ResearchItem::query()->with('snapshots')
            ->whereHas('snapshots')
            ->when($this->option('item'), fn ($query, $ids) => $query->whereIn('id', $ids))
            ->oldest()->limit(max(1, min(100, (int) $this->option('limit'))))->get();

        foreach ($items as $item) {
            $builder->build($item);
        }
        $this->info("Built or refreshed {$items->count()} editorial ".str('dossier')->plural($items->count()).'.');

        return self::SUCCESS;
    }
}
