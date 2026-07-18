<?php

namespace App\Jobs;

use App\Ai\Knowledge\KnowledgeIndexer;
use App\Models\CivicFaultReport;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Voucher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPublicKnowledge implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly string $sourceType, public readonly int $sourceId)
    {
        $this->afterCommit();
        $this->onQueue('ai-indexing');
    }

    public function handle(KnowledgeIndexer $indexer): void
    {
        $model = match ($this->sourceType) {
            'listing' => Listing::class,
            'event' => Event::class,
            'voucher' => Voucher::class,
            'classified' => Classified::class,
            'fault' => CivicFaultReport::class,
            default => null,
        };
        $record = $model ? $model::query()->find($this->sourceId) : null;

        $record ? $indexer->index($record) : $indexer->remove($this->sourceType, $this->sourceId);
    }
}
