<?php

namespace App\Console\Commands;

use App\Ai\Editorial\SecureSourceFetcher;
use App\Models\ResearchItem;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SnapshotResearchSourcesCommand extends Command
{
    protected $signature = 'life:research:snapshot {--item=* : Research item IDs} {--limit=20}';

    protected $description = 'Securely acquire immutable evidence snapshots for collected research items.';

    public function handle(SecureSourceFetcher $fetcher): int
    {
        $items = ResearchItem::query()->with('researchSource')->whereDoesntHave('snapshots')
            ->whereNotNull('source_url')
            ->when($this->option('item'), fn ($query, $ids) => $query->whereIn('id', $ids))
            ->oldest()->limit(max(1, min(100, (int) $this->option('limit'))))->get();
        $created = $failed = 0;

        foreach ($items as $item) {
            try {
                $fetcher->snapshot($item);
                $created++;
            } catch (\Throwable $exception) {
                $item->update([
                    'status' => ResearchItem::STATUS_FAILED,
                    'raw_payload' => array_merge($item->raw_payload ?: [], ['snapshot_error' => Str::limit($exception->getMessage(), 500, '')]),
                ]);
                $failed++;
            }
        }

        $this->info("Research snapshots: {$created} created, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
