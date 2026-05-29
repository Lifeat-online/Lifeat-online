<?php

namespace App\Console\Commands;

use App\Models\MallOrder;
use Illuminate\Console\Command;

class MallCancelAbandonedOrdersCommand extends Command
{
    protected $signature = 'mall:orders:cancel-abandoned {--hours=2}';

    protected $description = 'Cancel pending mall orders that have not received a PayFast ITN.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));

        $count = MallOrder::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subHours($hours))
            ->update(['status' => 'cancelled']);

        $this->info("Cancelled {$count} abandoned mall orders.");

        return self::SUCCESS;
    }
}
