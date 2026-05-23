<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pageTitle }}</h2>
                <p class="mt-1 text-sm text-gray-500">Create a listing-linked advert campaign for package checkout and admin approval.</p>
            </div>
            <a href="{{ route('admin.campaigns.ads.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Back to ad campaigns</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="post" action="{{ $formAction }}" enctype="multipart/form-data" class="rounded-lg bg-white p-6 shadow-sm">
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
                        <label for="destination_url" class="block text-sm font-medium text-gray-700">Destination URL</label>
                        <input id="destination_url" name="destination_url" class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('destination_url', $campaign->destination_url) }}">
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                            @foreach (['draft', 'ready', 'active', 'paused'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $campaign->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="placement" class="block text-sm font-medium text-gray-700">Placement</label>
                        <select id="placement" name="placement" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                            @foreach ([
                                'banner' => 'Section banner',
                                'sitewide_banner' => 'Sitewide banner',
                                'in_article_intro' => 'After article intro',
                                'in_article_mid' => 'Between article sections',
                                'in_article_end' => 'After article',
                                'popup' => 'Promotional pop-up',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected(old('placement', $campaign->placement ?: 'banner') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="creative_image_upload" class="block text-sm font-medium text-gray-700">Creative image</label>
                        <input id="creative_image_upload" name="creative_image_upload" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-700">
                    </div>

                    <div>
                        <label for="start_at" class="block text-sm font-medium text-gray-700">Start date</label>
                        <input id="start_at" name="start_at" type="datetime-local" class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('start_at', optional($campaign->start_at)->format('Y-m-d\TH:i')) }}">
                    </div>

                    <div>
                        <label for="end_at" class="block text-sm font-medium text-gray-700">End date</label>
                        <input id="end_at" name="end_at" type="datetime-local" class="mt-1 block w-full rounded-md border-gray-300 text-sm" value="{{ old('end_at', optional($campaign->end_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                </div>

                <div class="mt-5">
                    @include('partials.ai-copy-assistant', [
                        'endpoint' => route('admin.ai.ad-copy'),
                        'mode' => 'ad',
                        'heading' => 'AI Advert Copy',
                        'description' => 'Draft a campaign title, headline, advert body, and call to action from this listing and offer.',
                        'placeholder' => 'Example: winter tyre special, target Bethlehem drivers, friendly and direct.',
                    ])
                </div>

                <div class="mt-5">
                    <label for="body" class="block text-sm font-medium text-gray-700">Creative copy</label>
                    <textarea id="body" name="body" rows="6" class="mt-1 block w-full rounded-md border-gray-300 text-sm">{{ old('body', $campaign->body) }}</textarea>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white" type="submit">Create ad campaign</button>
                    <a href="{{ route('admin.campaigns.ads.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
