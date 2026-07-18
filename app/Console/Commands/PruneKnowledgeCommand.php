<?php

namespace App\Console\Commands;

use App\Models\KnowledgeDocument;
use Illuminate\Console\Command;

class PruneKnowledgeCommand extends Command
{
    protected $signature = 'life:knowledge:prune';

    protected $description = 'Remove knowledge documents whose public eligibility has expired';

    public function handle(): int
    {
        $deleted = KnowledgeDocument::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->delete();

        $this->info("Pruned {$deleted} expired knowledge document(s).");

        return self::SUCCESS;
    }
}
