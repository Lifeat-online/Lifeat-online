@php use Illuminate\Support\Facades\Storage; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Review Classified</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $classified->title }}{{ $classified->user ? ' • '.$classified->user->name : '' }}</p>
            </div>
            <a href="{{ route('admin.classifieds.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Back to queue</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Classified Content</h3>
                        <div class="mt-4 space-y-4">
                            @if ($classified->featured_image)
                                <img src="{{ Storage::disk('public')->url($classified->featured_image) }}" alt="" class="w-full rounded-lg object-cover" style="max-height: 360px;">
                            @endif
                            <div class="grid gap-4 md:grid-cols-2 text-sm">
                                <div>
                                    <p class="text-gray-500">Price</p>
                                    <p class="font-medium text-gray-900">
                                        @if ($classified->contact_for_price)
                                            Contact for price
                                        @elseif (! is_null($classified->price))
                                            {{ $classified->currency }} {{ number_format((float) $classified->price, 2) }}
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Location</p>
                                    <p class="font-medium text-gray-900">{{ collect([$classified->city, $classified->region, $classified->country])->filter()->join(', ') ?: '-' }}</p>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Description</p>
                                <div class="mt-2 rounded-md bg-gray-50 p-4 text-sm text-gray-700 whitespace-pre-line">{{ $classified->description ?: 'No description provided.' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Moderation Status</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div>
                                <p class="text-gray-500">Current status</p>
                                <p class="font-medium text-gray-900">{{ ucfirst($classified->status) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Owner</p>
                                <p class="font-medium text-gray-900">{{ $classified->user?->email ?: 'Guest submission' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Submitted</p>
                                <p class="font-medium text-gray-900">{{ optional($classified->submitted_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Reviewed</p>
                                <p class="font-medium text-gray-900">{{ optional($classified->reviewed_at)->format('j M Y H:i') ?: '-' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Reviewed by</p>
                                <p class="font-medium text-gray-900">{{ $classified->reviewer?->name ?: '-' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Update Moderation</h3>
                        <form method="post" action="{{ route('admin.classifieds.review', $classified) }}" class="mt-4 space-y-4">
                            @csrf
                            <div>
                                <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Decision</label>
                                <select id="status" name="status" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    @foreach ($decisionOptions as $status)
                                        <option value="{{ $status }}" @selected(old('status', $classified->status) === $status)>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="moderation_notes" class="mb-1 block text-sm font-medium text-gray-700">Moderation notes</label>
                                <textarea id="moderation_notes" name="moderation_notes" rows="8" class="w-full rounded-md border-gray-300 text-sm shadow-sm">{{ old('moderation_notes', $classified->moderation_notes) }}</textarea>
                            </div>
                            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Save moderation decision</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
