<?php

namespace App\Events;

use App\Models\TransportRequestOffer;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransportRequestOffered implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public TransportRequestOffer $offer)
    {
        $this->offer->loadMissing(['request', 'vehicle']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('transport.driver.'.$this->offer->transport_driver_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'transport.request.offered';
    }

    public function broadcastWith(): array
    {
        return [
            'offer_id' => $this->offer->id,
            'request_id' => $this->offer->transport_request_id,
            'request_number' => $this->offer->request->request_number,
            'service_type' => $this->offer->request->service_type,
            'pickup_address' => $this->offer->request->pickup_address,
            'dropoff_address' => $this->offer->request->dropoff_address,
            'quoted_amount' => (float) $this->offer->quoted_amount,
            'driver_amount' => (float) $this->offer->driver_amount,
            'vehicle_name' => $this->offer->vehicle->name,
        ];
    }
}
