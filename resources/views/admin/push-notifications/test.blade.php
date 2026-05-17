<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Push Notifications</h2>
                <p class="mt-1 text-sm text-gray-500">Compose, test, and dispatch rich browser push notifications.</p>
            </div>
            <a href="{{ route('admin.campaigns.push.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">Push Campaigns</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
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
                    <p class="text-sm text-gray-500">This Browser</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $ownSubscriptionCount }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900" data-push-toggle hidden>Enable alerts</button>
                        <button type="button" class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100" data-push-tone-preview hidden>Preview tone</button>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.push-notifications.store') }}" class="space-y-6">
                @csrf

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Message</h3>
                            <p class="mt-1 text-sm text-gray-500">Send to your browser first, then switch to all active subscribers when the payload is right.</p>
                        </div>
                        <div class="w-full sm:w-64">
                            <label for="audience" class="block text-sm font-medium text-gray-700">Audience</label>
                            <select id="audience" name="audience" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="self" @selected(old('audience', 'self') === 'self')>Only my browser subscriptions</option>
                                <option value="all" @selected(old('audience') === 'all')>All active subscribers</option>
                            </select>
                            <x-input-error :messages="$errors->get('audience')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="80" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <div>
                            <label for="url" class="block text-sm font-medium text-gray-700">Click URL</label>
                            <input id="url" name="url" type="url" value="{{ old('url', route('admin.dashboard')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('url')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <label for="body" class="block text-sm font-medium text-gray-700">Message</label>
                            <textarea id="body" name="body" rows="3" maxlength="180" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body') }}</textarea>
                            <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Visuals</h3>
                        <div class="mt-5 grid gap-5">
                            <div>
                                <label for="icon" class="block text-sm font-medium text-gray-700">Icon URL</label>
                                <input id="icon" name="icon" type="url" value="{{ old('icon', asset('pwa/icon-192.png')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('icon')" class="mt-2" />
                            </div>

                            <div>
                                <label for="badge" class="block text-sm font-medium text-gray-700">Badge URL</label>
                                <input id="badge" name="badge" type="url" value="{{ old('badge', asset('pwa/favicon-32x32.png')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('badge')" class="mt-2" />
                            </div>

                            <div>
                                <label for="image" class="block text-sm font-medium text-gray-700">Hero Image URL</label>
                                <input id="image" name="image" type="url" value="{{ old('image') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('image')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Delivery</h3>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="urgency" class="block text-sm font-medium text-gray-700">Urgency</label>
                                <select id="urgency" name="urgency" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach (['normal' => 'Normal', 'high' => 'High', 'low' => 'Low', 'very-low' => 'Very low'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('urgency', 'normal') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('urgency')" class="mt-2" />
                            </div>

                            <div>
                                <label for="ttl" class="block text-sm font-medium text-gray-700">TTL seconds</label>
                                <input id="ttl" name="ttl" type="number" min="0" max="2419200" value="{{ old('ttl', 86400) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('ttl')" class="mt-2" />
                            </div>

                            <div>
                                <label for="tag" class="block text-sm font-medium text-gray-700">Notification tag</label>
                                <input id="tag" name="tag" type="text" value="{{ old('tag', 'admin-broadcast') }}" maxlength="80" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('tag')" class="mt-2" />
                            </div>

                            <div>
                                <label for="topic" class="block text-sm font-medium text-gray-700">Web Push topic</label>
                                <input id="topic" name="topic" type="text" value="{{ old('topic', 'admin-broadcast') }}" maxlength="32" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('topic')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Behavior</h3>
                        <div class="mt-5 grid gap-4">
                            <label class="flex items-start gap-3">
                                <input name="require_interaction" value="1" type="checkbox" @checked(old('require_interaction')) class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span><span class="block text-sm font-medium text-gray-800">Require interaction</span><span class="block text-sm text-gray-500">Keep supported desktop notifications visible until clicked or dismissed.</span></span>
                            </label>
                            <label class="flex items-start gap-3">
                                <input name="renotify" value="1" type="checkbox" @checked(old('renotify', true)) class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span><span class="block text-sm font-medium text-gray-800">Re-notify matching tag</span><span class="block text-sm text-gray-500">Alert again when replacing an existing notification with the same tag.</span></span>
                            </label>
                            <label class="flex items-start gap-3">
                                <input name="silent" value="1" type="checkbox" @checked(old('silent')) class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span><span class="block text-sm font-medium text-gray-800">Silent notification</span><span class="block text-sm text-gray-500">Suppress browser/OS sound and vibration where supported.</span></span>
                            </label>
                            <label class="flex items-start gap-3">
                                <input name="play_tone" value="1" type="checkbox" @checked(old('play_tone', true)) class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span><span class="block text-sm font-medium text-gray-800">Play app tone in open tabs</span><span class="block text-sm text-gray-500">Uses Web Audio when Lifeat is open; closed-background sound is controlled by the device.</span></span>
                            </label>
                        </div>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="tone" class="block text-sm font-medium text-gray-700">Tone</label>
                                <select id="tone" name="tone" required data-push-tone-select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach (['chime' => 'Chime', 'bell' => 'Bell', 'urgent' => 'Urgent', 'soft' => 'Soft'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('tone', 'chime') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('tone')" class="mt-2" />
                            </div>

                            <div>
                                <label for="vibration" class="block text-sm font-medium text-gray-700">Vibration</label>
                                <select id="vibration" name="vibration" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach (['none' => 'None', 'short' => 'Short', 'double' => 'Double', 'urgent' => 'Urgent'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('vibration', 'double') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('vibration')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">Actions</h3>
                        <div class="mt-5 grid gap-5">
                            @foreach ([1, 2] as $index)
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="action_{{ $index }}_title" class="block text-sm font-medium text-gray-700">Action {{ $index }} label</label>
                                        <input id="action_{{ $index }}_title" name="action_{{ $index }}_title" type="text" value="{{ old("action_{$index}_title") }}" maxlength="24" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <x-input-error :messages="$errors->get('action_'.$index.'_title')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="action_{{ $index }}_url" class="block text-sm font-medium text-gray-700">Action {{ $index }} URL</label>
                                        <input id="action_{{ $index }}_url" name="action_{{ $index }}_url" type="url" value="{{ old("action_{$index}_url") }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <x-input-error :messages="$errors->get('action_'.$index.'_url')" class="mt-2" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700" onclick="return document.getElementById('audience')?.value !== 'all' || confirm('Send this push notification to all active subscribers now?');">Send Push</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
