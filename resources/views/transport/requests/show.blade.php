<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Transport Request {{ $transportRequest->request_number }}</h2>
    </x-slot>

    @php($realtimeStatuses = [\App\Models\TransportRequest::STATUS_DISPATCHING, \App\Models\TransportRequest::STATUS_ACCEPTED, \App\Models\TransportRequest::STATUS_DRIVER_ARRIVING, \App\Models\TransportRequest::STATUS_IN_TRANSIT])

    <div class="py-10" @if (in_array($transportRequest->status, $realtimeStatuses, true)) data-transport-realtime data-channel="transport.request.{{ $transportRequest->id }}" @endif>
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ session('status') }}</div>
            @endif
            @if (in_array($transportRequest->status, $realtimeStatuses, true))
                <div data-transport-notice class="rounded-md bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    Live tracking updates are active for this request.
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm uppercase tracking-wide text-gray-500">{{ ucfirst(str_replace('_', ' ', $transportRequest->service_type)) }}</p>
                        <h3 data-transport-status class="mt-1 text-xl font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $transportRequest->status)) }}</h3>
                        <p class="mt-2 text-sm text-gray-600">{{ $transportRequest->pickup_address }} to {{ $transportRequest->dropoff_address }}</p>
                        @if ($transportRequest->scheduled_pickup_at)
                            <p class="mt-1 text-sm text-gray-600">Scheduled pickup: {{ $transportRequest->scheduled_pickup_at->format('Y-m-d H:i') }}</p>
                        @endif
                    </div>
                    <div class="rounded-md bg-slate-50 p-4 text-sm">
                        <p><span class="text-gray-500">Quote:</span> <strong>ZAR {{ number_format((float) $transportRequest->quoted_amount, 2) }}</strong></p>
                        <p><span class="text-gray-500">Platform 10%:</span> ZAR {{ number_format((float) $transportRequest->platform_fee, 2) }}</p>
                        <p><span class="text-gray-500">Driver:</span> ZAR {{ number_format((float) $transportRequest->driver_amount, 2) }}</p>
                    </div>
                </div>

                @if ($transportRequest->acceptedDriver)
                    <div class="mt-6 rounded-md border border-green-200 bg-green-50 p-4">
                        <p class="font-semibold text-green-950">Driver assigned: {{ $transportRequest->acceptedDriver->user->name }}</p>
                        <p class="mt-1 text-sm text-green-900">Vehicle: {{ $transportRequest->acceptedVehicle?->name }} · {{ ucfirst($transportRequest->acceptedVehicle?->vehicle_type ?? 'vehicle') }}</p>
                    </div>
                @elseif ($transportRequest->status === \App\Models\TransportRequest::STATUS_SCHEDULED)
                    <div class="mt-6 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        No driver is currently assigned. This request is scheduled and will be dispatched when drivers are available.
                    </div>
                @else
                    <div class="mt-6 rounded-md border border-dashed border-gray-300 p-4 text-sm text-gray-600">
                        Waiting for an available driver to accept. Realtime tracking is only opened while there is active driver/request activity.
                    </div>
                @endif
            </section>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Driver offers</h3>
                <div class="mt-4 grid gap-3">
                    @forelse ($transportRequest->offers as $offer)
                        <div class="rounded-md border border-gray-200 p-4 text-sm">
                            <p class="font-semibold text-gray-900">{{ $offer->driver->user->name }} · {{ $offer->vehicle->name }}</p>
                            <p class="mt-1 text-gray-600">Status: {{ ucfirst($offer->status) }} · Quote: ZAR {{ number_format((float) $offer->quoted_amount, 2) }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">No eligible drivers were available when this request was created.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Status history</h3>
                <div class="mt-4 space-y-3">
                    @foreach ($transportRequest->statusEvents as $event)
                        <div class="text-sm">
                            <p class="font-semibold text-gray-800">{{ ucfirst(str_replace('_', ' ', $event->status)) }}</p>
                            <p class="text-gray-600">{{ $event->notes }} · {{ $event->created_at->diffForHumans() }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
