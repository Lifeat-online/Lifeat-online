@php use Illuminate\Support\Facades\Storage; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ad Campaign: {{ $campaign->title }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $campaign->listing?->title }} • {{ $campaign->owner?->email }}</p>
            </div>
            <a href="{{ route('admin.campaigns.ads.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Back to list</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Campaign content ─────────────────────────────────────── --}}
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Creative</h3>
                        @if ($campaign->creative_image)
                            <div class="mt-4">
                                <img src="{{ Storage::disk('public')->url($campaign->creative_image) }}" alt="Creative image" class="rounded-lg max-h-72 object-contain border">
                            </div>
                        @else
                            <p class="mt-2 text-sm text-gray-500">No creative image uploaded.</p>
                        @endif
                        <div class="mt-4 space-y-3 text-sm">
                            @if ($campaign->headline)
                                <div><p class="text-gray-500">Headline</p><p class="font-medium">{{ $campaign->headline }}</p></div>
                            @endif
                            @if ($campaign->body)
                                <div><p class="text-gray-500">Body copy</p><p class="whitespace-pre-line text-gray-700">{{ $campaign->body }}</p></div>
                            @endif
                            @if ($campaign->destination_url)
                                <div><p class="text-gray-500">Destination URL</p><a href="{{ $campaign->destination_url }}" target="_blank" class="text-indigo-600 break-all">{{ $campaign->destination_url }}</a></div>
                            @endif
                        </div>
                    </div>

                    {{-- Analytics ───────────────────────────────────────── --}}
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Performance</h3>
                        <div class="mt-4 grid gap-4 sm:grid-cols-3 text-center">
                            <div>
                                <p class="text-2xl font-bold text-indigo-700">{{ number_format($campaign->impressions) }}</p>
                                <p class="text-sm text-gray-500">Impressions</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-green-700">{{ number_format($campaign->clicks) }}</p>
                                <p class="text-sm text-gray-500">Clicks</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold">{{ $campaign->ctr() }}%</p>
                                <p class="text-sm text-gray-500">CTR</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Campaign Details</h3>
                        <div class="mt-4 grid gap-4 md:grid-cols-2 text-sm">
                            <div><p class="text-gray-500">Listing</p><p class="font-medium">{{ $campaign->listing?->title ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Linked event</p><p class="font-medium">{{ $campaign->event?->title ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Start date</p><p class="font-medium">{{ optional($campaign->start_at)->format('j M Y') ?: '-' }}</p></div>
                            <div><p class="text-gray-500">End date</p><p class="font-medium">{{ optional($campaign->end_at)->format('j M Y') ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Package</p><p class="font-medium">{{ $campaign->activeSubscription?->package?->name ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Package expires</p><p class="font-medium">{{ optional($campaign->package_expires_at)->format('j M Y') ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Published at</p><p class="font-medium">{{ optional($campaign->published_at)->format('j M Y H:i') ?: '-' }}</p></div>
                            <div><p class="text-gray-500">Owner</p><p class="font-medium">{{ $campaign->owner?->email ?: '-' }}</p></div>
                        </div>
                    </div>
                </div>

                {{-- Admin actions sidebar ───────────────────────────────── --}}
                <div class="space-y-6">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Status</h3>
                        <div class="mt-3">
                            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold
                                @if($campaign->status === 'active') bg-green-100 text-green-800
                                @elseif($campaign->status === 'ready') bg-amber-100 text-amber-800
                                @elseif($campaign->status === 'paused') bg-gray-100 text-gray-600
                                @else bg-slate-100 text-slate-600 @endif">
                                {{ ucfirst($campaign->status) }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm space-y-3">
                        <h3 class="text-lg font-semibold text-gray-900">Admin Actions</h3>

                        @if (in_array($campaign->status, ['ready', 'paused']))
                            <form method="post" action="{{ route('admin.campaigns.ads.approve', $campaign) }}">
                                @csrf
                                <button class="w-full rounded-md bg-green-600 px-4 py-2 text-sm text-white">
                                    {{ $campaign->status === 'paused' ? 'Resume Campaign' : 'Approve & Activate' }}
                                </button>
                            </form>
                        @endif

                        @if ($campaign->status === 'active')
                            <form method="post" action="{{ route('admin.campaigns.ads.pause', $campaign) }}">
                                @csrf
                                <button class="w-full rounded-md bg-amber-600 px-4 py-2 text-sm text-white">Pause Campaign</button>
                            </form>
                        @endif

                        @if ($campaign->status === 'paused')
                            <form method="post" action="{{ route('admin.campaigns.ads.resume', $campaign) }}">
                                @csrf
                                <button class="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Resume Campaign</button>
                            </form>
                        @endif

                        @if (in_array($campaign->status, ['draft']))
                            <p class="text-sm text-gray-500">Campaign is in draft. The owner must mark it ready before admin review.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
