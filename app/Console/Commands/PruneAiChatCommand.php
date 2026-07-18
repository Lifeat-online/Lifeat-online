<?php

namespace App\Console\Commands;

use App\Models\AiChatSession;
use Illuminate\Console\Command;

class PruneAiChatCommand extends Command
{
    protected $signature = 'life:ai-chat:prune';

    protected $description = 'Delete Ask Life conversations past their configured retention expiry.';

    public function handle(): int
    {
        $deleted = AiChatSession::query()->where('expires_at', '<=', now())->delete();
        $this->info("Deleted {$deleted} expired Ask Life ".str('conversation')->plural($deleted).'.');

        return self::SUCCESS;
    }
}
