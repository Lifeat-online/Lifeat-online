<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pageTitle }}</h2>
                <p class="mt-1 text-sm text-gray-500">Create a listing-linked push campaign for scheduling, package checkout, and dispatch.</p>
            </div>
            <a href="{{ route('admin.campaigns.push.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Back to push campaigns</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            @if (auth()->user()?->email === 'jameskoen78@gmail.com')
                <form method="POST" action="{{ route('admin.push-notifications.store') }}" class="rounded-lg bg-white p-6 shadow-sm">
                    @csrf
                    <input type="hidden" name="audience" value="all">
                    <input type="hidden" name="url" value="{{ route('admin.dashboard') }}">

                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Platform Push</h3>
                            <p class="mt-1 text-sm text-gray-500">Send a Dev/platform notification without attaching it to a business listing.</p>
                        </div>
                        <a href="{{ route('admin.push-notifications.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Advanced sender</a>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="platform_title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input id="platform_title" name="title" maxlength="80" required class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('title') }}">
                        </div>
                        <div>
                            <label for="platform_body" class="block text-sm font-medium text-gray-700">Message</label>
                            <input id="platform_body" name="body" maxlength="180" required class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('body') }}">
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white" onclick="return confirm('Send this platform push to all active subscribers now?');">Send platform push now</button>
                    </div>
                </form>
            @endif

            <form method="post" action="{{ $formAction }}" class="rounded-lg bg-white p-6 shadow-sm">
                @csrf

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="listing_id" class="block text-sm font-medium text-gray-700">Business listing</label>
                        <select id="listing_id" name="listing_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                            <option value="">Select a listing</option>
                            @foreach ($listings as $listing)
                                <option value="{{ $listing->id }}" @selected((string) old('listing_id', $campaign->listing_id) === (string) $listing->id)>
                                    {{ $listing->title }}{{ $listing->owner?->email ? ' - '.$listing->owner->email : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="event_id" class="block text-sm font-medium text-gray-700">Linked event</label>
                        <select id="event_id" name="event_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">No linked event</option>
                            @foreach ($events as $event)
                                <option value="{{ $event->id }}" @selected((string) old('event_id', $campaign->event_id) === (string) $event->id)>
                                    {{ $event->title }}{{ $event->listing?->title ? ' - '.$event->listing->title : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Campaign title</label>
                        <input id="title" name="title" class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('title', $campaign->title) }}" required>
                    </div>

                    <div>
                        <label for="headline" class="block text-sm font-medium text-gray-700">Headline</label>
                        <input id="headline" name="headline" class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('headline', $campaign->headline) }}">
                    </div>

                    <div>
                        <label for="schedule_at" class="block text-sm font-medium text-gray-700">Schedule</label>
                        <input id="schedule_at" name="schedule_at" type="datetime-local" class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('schedule_at', optional($campaign->schedule_at)->format('Y-m-d\TH:i')) }}">
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                            @foreach (['draft', 'ready', 'scheduled', 'active'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $campaign->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="audience_scope" class="block text-sm font-medium text-gray-700">Audience scope</label>
                        <select id="audience_scope" name="audience_scope" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                            @foreach (['listing_city' => 'Listing city', 'listing_region' => 'Listing region', 'custom_radius' => 'Custom radius'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('audience_scope', $campaign->audience_scope ?: 'listing_city') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="target_city" class="block text-sm font-medium text-gray-700">Target city</label>
                        <input id="target_city" name="target_city" class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('target_city', $campaign->target_city) }}">
                    </div>

                    <div>
                        <label for="target_region" class="block text-sm font-medium text-gray-700">Target region</label>
                        <input id="target_region" name="target_region" class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('target_region', $campaign->target_region) }}">
                    </div>

                    <div>
                        <label for="radius_km" class="block text-sm font-medium text-gray-700">Radius (km)</label>
                        <input id="radius_km" name="radius_km" type="number" min="1" max="200" class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('radius_km', $campaign->radius_km) }}">
                    </div>
                </div>

                <div class="mt-5">
                    <label for="message" class="block text-sm font-medium text-gray-700">Push message</label>
                    <textarea id="message" name="message" rows="6" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>{{ old('message', $campaign->message) }}</textarea>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Create push campaign</button>
                    <a href="{{ route('admin.campaigns.push.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
