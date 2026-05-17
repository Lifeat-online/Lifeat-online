<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Push Notifications</h2>
                <p class="mt-1 text-sm text-gray-500">Send a browser push from the backend to active subscribers.</p>
            </div>
            <a href="{{ route('admin.campaigns.push.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Push Campaigns</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('push'))
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    {{ $errors->first('push') }}
                </div>
            @endif

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-500">VAPID Keys</p>
                    <p class="mt-2 text-2xl font-semibold {{ $isConfigured ? 'text-emerald-700' : 'text-red-700' }}">{{ $isConfigured ? 'Ready' : 'Missing' }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-500">Active Subscriptions</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $activeSubscriptionCount }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-500">Your Browser Subscriptions</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $ownSubscriptionCount }}</p>
                    <button type="button" class="mt-3 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900" data-push-toggle hidden>Enable alerts</button>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.push-notifications.store') }}" class="rounded-lg bg-white p-6 shadow-sm">
                @csrf

                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Send Push</h3>
                    <p class="mt-1 text-sm text-gray-500">Use the audience selector to send to everyone with active browser alerts, or send only to your own browser first.</p>
                </div>

                <div class="mt-6 grid gap-5">
                    <div>
                        <label for="audience" class="block text-sm font-medium text-gray-700">Audience</label>
                        <select id="audience" name="audience" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all" @selected(old('audience', 'all') === 'all')>All active subscribers</option>
                            <option value="self" @selected(old('audience') === 'self')>Only my browser subscriptions</option>
                        </select>
                        <x-input-error :messages="$errors->get('audience')" class="mt-2" />
                    </div>

                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="80" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <label for="body" class="block text-sm font-medium text-gray-700">Message</label>
                        <textarea id="body" name="body" rows="3" maxlength="180" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    </div>

                    <div>
                        <label for="url" class="block text-sm font-medium text-gray-700">Click URL</label>
                        <input id="url" name="url" type="url" value="{{ old('url', route('admin.dashboard')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <x-input-error :messages="$errors->get('url')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700" onclick="return document.getElementById('audience')?.value !== 'all' || confirm('Send this push notification to all active subscribers now?');">Send Push</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
