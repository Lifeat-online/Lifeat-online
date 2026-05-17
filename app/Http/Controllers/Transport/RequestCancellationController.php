<?php

namespace App\Http\Controllers\Transport;

use App\Events\TransportRequestStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\TransportDutySession;
use App\Models\TransportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestCancellationController extends Controller
{
    public function store(Request $request, TransportRequest $transportRequest): RedirectResponse
    {
        abort_unless($transportRequest->user_id === $request->user()->id, 403);

        $cancelledRequest = DB::transaction(function () use ($request, $transportRequest) {
            $lockedRequest = TransportRequest::with('acceptedVehicle')
                ->whereKey($transportRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(in_array($lockedRequest->status, [
                TransportRequest::STATUS_COMPLETED,
                TransportRequest::STATUS_CANCELLED,
            ], true), 422, 'This request can no longer be cancelled.');

            $fee = $lockedRequest->accepted_transport_driver_id
                ? (float) ($lockedRequest->acceptedVehicle?->cancellation_fee ?? 0)
                : 0.0;

            $lockedRequest->update([
                'status' => TransportRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_fee' => $fee,
            ]);

            if ($lockedRequest->accepted_transport_driver_id) {
                TransportDutySession::where('transport_driver_id', $lockedRequest->accepted_transport_driver_id)
                    ->whereNull('ended_at')
                    ->where('status', TransportDutySession::STATUS_BUSY)
                    ->update([
                        'status' => TransportDutySession::STATUS_AVAILABLE,
                        'last_seen_at' => now(),
                    ]);
            }

            $lockedRequest->statusEvents()->create([
                'actor_user_id' => $request->user()->id,
                'status' => TransportRequest::STATUS_CANCELLED,
                'notes' => $fee > 0
                    ? 'Passenger cancelled after driver acceptance. Cancellation fee applies: ZAR '.number_format($fee, 2).'.'
                    : 'Passenger cancelled before driver acceptance. No cancellation fee applies.',
            ]);

            return $lockedRequest->fresh();
        });

        event(new TransportRequestStatusChanged($cancelledRequest, $cancelledRequest->cancellation_fee > 0
            ? 'Request cancelled. Cancellation fee applies.'
            : 'Request cancelled before driver acceptance.'));

        return redirect()->route('transport.requests.show', $transportRequest)
            ->with('status', $cancelledRequest->cancellation_fee > 0
                ? 'Request cancelled. Cancellation fee: ZAR '.number_format((float) $cancelledRequest->cancellation_fee, 2).'.'
                : 'Request cancelled before driver acceptance. No cancellation fee applies.');
    }
}
