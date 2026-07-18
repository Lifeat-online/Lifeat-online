<?php

namespace App\Console\Commands;

use App\Ai\Knowledge\KnowledgeIndexer;
use App\Models\Article;
use App\Models\CivicFaultReport;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Models\Voucher;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class KnowledgeReindexCommand extends Command
{
    protected $signature = 'life:knowledge:reindex
        {--type=all : all|article|listing|event|voucher|classified|fault}
        {--id= : Reindex one source ID}
        {--locale= : Limit articles by source locale}
        {--after-id= : Resume after this source ID}
        {--chunk=100 : Database batch size}
        {--dry-run : Report eligible records without writing}';

    protected $description = 'Build or refresh the public AI knowledge index.';

    public function handle(KnowledgeIndexer $indexer): int
    {
        $type = (string) $this->option('type');
        $queries = $this->queries();
        if ($type !== 'all' && ! isset($queries[$type])) {
            $this->error('Unknown source type. Use all, article, listing, event, voucher, classified, or fault.');

            return self::INVALID;
        }

        $selected = $type === 'all' ? $queries : [$type => $queries[$type]];
        $indexed = 0;
        foreach ($selected as $sourceType => $query) {
            if ($id = $this->option('id')) {
                $query->whereKey($id);
            } elseif ($afterId = $this->option('after-id')) {
                $query->whereKey('>', $afterId);
            }
            if ($sourceType === 'article' && ($locale = $this->option('locale'))) {
                $query->where('source_locale', $locale);
            }

            $count = $query->count();
            $this->info("{$count} eligible ".str($sourceType)->plural($count).'.');
            if ($this->option('dry-run')) {
                continue;
            }

            $query->orderBy('id')->chunkById(max(1, (int) $this->option('chunk')), function ($records) use ($indexer, &$indexed): void {
                foreach ($records as $record) {
                    $indexer->index($record);
                    $indexed++;
                }
            });
        }

        if (! $this->option('dry-run')) {
            $this->info("Indexed {$indexed} public knowledge ".str('record')->plural($indexed).'.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, Builder> */
    private function queries(): array
    {
        return [
            'article' => Article::query()->published()->whereNotNull('published_at'),
            'listing' => Listing::query()->published(),
            'event' => Event::query()->published()->where(fn (Builder $query) => $query->whereNull('end_at')->orWhere('end_at', '>', now())),
            'voucher' => Voucher::query()->active()->whereHas('listing', fn (Builder $query) => $query->published()),
            'classified' => Classified::query()->where('status', Classified::STATUS_PUBLISHED)->whereNotNull('published_at'),
            'fault' => CivicFaultReport::query()->where('is_approved', true),
        ];
    }
}
