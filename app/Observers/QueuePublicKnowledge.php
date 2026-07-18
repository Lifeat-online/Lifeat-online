<?php

namespace App\Observers;

use App\Jobs\SyncPublicKnowledge;
use Illuminate\Database\Eloquent\Model;

class QueuePublicKnowledge
{
    public function saved(Model $model): void
    {
        $this->dispatch($model);
    }

    public function deleted(Model $model): void
    {
        $this->dispatch($model);
    }

    private function dispatch(Model $model): void
    {
        if (! config('ai_platform.knowledge.auto_index')) {
            return;
        }

        $type = match (class_basename($model)) {
            'Listing' => 'listing',
            'Event' => 'event',
            'Voucher' => 'voucher',
            'Classified' => 'classified',
            'CivicFaultReport' => 'fault',
            default => null,
        };

        if ($type) {
            SyncPublicKnowledge::dispatch($type, (int) $model->getKey());
        }
    }
}
