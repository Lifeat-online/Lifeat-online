<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Live Driver Workspace</h2>
    </x-slot>

    <div class="py-10" data-transport-realtime data-channel="transport.driver.{{ $driver->id }}">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            <div data-transport-notice class="rounded-md bg-slate-50 px-4 py-3 text-sm text-slate-700">
                Live transport updates are active while you are on duty.
            </div>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Available for requests</h3>
                        <p class="mt-1 text-sm text-gray-600">Vehicle: {{ $activeSession->vehicle->name }} · {{ ucfirst($activeSession->vehicle->vehicle_type) }}</p>
                    </div>
                    <form method="post" action="{{ route('transport.driver.clock-out') }}">
                        @csrf
                        <button class="rounded-md bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Clock out</button>
                    </form>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900">Incoming requests</h3>
                <div class="mt-5 grid gap-4">
                    @forelse ($offers as $offer)
                        <article class="rounded-md border border-gray-200 p-4">
                            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p class="text-sm uppercase tracking-wide text-gray-500">{{ ucfirst(str_replace('_', ' ', $offer->request->service_type)) }}</p>
                                    <h4 class="mt-1 font-semibold text-gray-900">{{ $offer->request->pickup_address }} to {{ $offer->request->dropoff_address }}</h4>
                                    <p class="mt-2 text-sm text-gray-600">{{ $offer->request->distance_km }} km · {{ ucfirst(str_replace('_', ' ', $offer->request->payment_method)) }}</p>
                                    @if ($offer->request->client_notes)
                                        <p class="mt-2 text-sm text-gray-600">{{ $offer->request->client_notes }}</p>
                                    @endif
                                </div>
                                <div class="md:text-right">
                                    <p class="text-lg font-semibold text-gray-900">ZAR {{ number_format((float) $offer->quoted_amount, 2) }}</p>
                                    <p class="text-sm text-gray-600">You earn ZAR {{ number_format((float) $offer->driver_amount, 2) }}</p>
                                    <form method="post" action="{{ route('transport.driver.offers.accept', $offer) }}" class="mt-3">
                                        @csrf
                                        <button class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white">Accept request</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-md border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                            Standing by for matching ride, parcel, and delivery requests.
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-red-200 bg-red-50 p-6">
                <h3 class="text-lg font-semibold text-red-950">Safety</h3>
                <p class="mt-2 text-sm text-red-900">The panic workflow will be connected to live safety events in the safety phase. Your configured emergency contact is already stored on the driver profile.</p>
                <button disabled class="mt-4 rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white opacity-60">Panic button coming next</button>
            </section>
        </div>
    </div>
</x-app-layout>
