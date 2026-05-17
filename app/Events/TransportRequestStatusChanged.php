<?php

namespace App\Events;

use App\Models\TransportRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransportRequestStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public TransportRequest $transportRequest, public string $note = '')
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('transport.request.'.$this->transportRequest->id),
            new PrivateChannel('transport.manager'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'transport.request.status';
    }

    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->transportRequest->id,
            'request_number' => $this->transportRequest->request_number,
            'status' => $this->transportRequest->status,
            'note' => $this->note,
        ];
    }
}
