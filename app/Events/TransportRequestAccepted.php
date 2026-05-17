<?php

namespace App\Events;

use App\Models\TransportRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransportRequestAccepted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public TransportRequest $transportRequest)
    {
        $this->transportRequest->loadMissing(['acceptedDriver.user', 'acceptedVehicle']);
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
        return 'transport.request.accepted';
    }

    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->transportRequest->id,
            'request_number' => $this->transportRequest->request_number,
            'status' => $this->transportRequest->status,
            'driver_name' => $this->transportRequest->acceptedDriver?->user?->name,
            'vehicle_name' => $this->transportRequest->acceptedVehicle?->name,
            'vehicle_type' => $this->transportRequest->acceptedVehicle?->vehicle_type,
        ];
    }
}
