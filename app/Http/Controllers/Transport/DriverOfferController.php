<?php

namespace App\Http\Controllers\Transport;

use App\Events\TransportRequestAccepted;
use App\Http\Controllers\Controller;
use App\Models\TransportDutySession;
use App\Models\TransportRequest;
use App\Models\TransportRequestOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverOfferController extends Controller
{
    public function accept(Request $request, TransportRequestOffer $offer): RedirectResponse
    {
        $driver = $request->user()->transportDriver()->with('activeDutySession')->firstOrFail();

        abort_unless($offer->transport_driver_id === $driver->id, 403);
        abort_unless($driver->activeDutySession?->id === $offer->transport_duty_session_id, 403);

        $acceptedRequest = DB::transaction(function () use ($request, $offer) {
            $lockedRequest = TransportRequest::whereKey($offer->transport_request_id)->lockForUpdate()->firstOrFail();
            $lockedOffer = TransportRequestOffer::whereKey($offer->id)->lockForUpdate()->firstOrFail();

            if ($lockedRequest->status !== TransportRequest::STATUS_DISPATCHING || $lockedRequest->accepted_transport_driver_id !== null) {
                return null;
            }

            if ($lockedOffer->status !== TransportRequestOffer::STATUS_OFFERED) {
                return null;
            }

            $lockedRequest->update([
                'status' => TransportRequest::STATUS_ACCEPTED,
                'accepted_transport_driver_id' => $lockedOffer->transport_driver_id,
                'accepted_transport_vehicle_id' => $lockedOffer->transport_vehicle_id,
                'quoted_amount' => $lockedOffer->quoted_amount,
                'platform_fee' => $lockedOffer->platform_fee,
                'driver_amount' => $lockedOffer->driver_amount,
                'accepted_at' => now(),
            ]);

            $lockedOffer->update([
                'status' => TransportRequestOffer::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            TransportRequestOffer::where('transport_request_id', $lockedRequest->id)
                ->whereKeyNot($lockedOffer->id)
                ->where('status', TransportRequestOffer::STATUS_OFFERED)
                ->update(['status' => TransportRequestOffer::STATUS_EXPIRED]);

            TransportDutySession::whereKey($lockedOffer->transport_duty_session_id)->update([
                'status' => TransportDutySession::STATUS_BUSY,
                'last_seen_at' => now(),
            ]);

            $lockedRequest->statusEvents()->create([
                'actor_user_id' => $request->user()->id,
                'status' => TransportRequest::STATUS_ACCEPTED,
                'notes' => 'Driver accepted the transport request.',
            ]);

            return $lockedRequest->fresh(['acceptedDriver.user', 'acceptedVehicle']);
        });

        if ($acceptedRequest) {
            event(new TransportRequestAccepted($acceptedRequest));
        }

        return redirect()->route('transport.driver.workspace')
            ->with('status', $acceptedRequest ? 'Request accepted.' : 'This request is no longer available.');
    }
}
