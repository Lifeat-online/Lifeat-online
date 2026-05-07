<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $pageTitle }}</h2>
            <a href="{{ route('admin.integrations.index') }}" class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Back to integrations</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="p-6">
                    @if (session('status'))<div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>@endif
                    @if ($errors->any())<div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700">Please correct the highlighted fields.</div>@endif

                    <form method="post" action="{{ $formAction }}" class="space-y-6">
                        @csrf
                        @if ($formMethod !== 'POST') @method($formMethod) @endif

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Listing</label>
                                <select name="listing_id" class="mt-1 w-full rounded-md border-gray-300">
                                    @foreach ($listings as $listing)
                                        <option value="{{ $listing->id }}" @selected((int) old('listing_id', $integration->listing_id) === (int) $listing->id)>{{ $listing->title }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('listing_id')" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Status</label>
                                <select name="status" class="mt-1 w-full rounded-md border-gray-300">
                                    <option value="inactive" @selected(old('status', $integration->status ?: 'inactive') === 'inactive')>Inactive</option>
                                    <option value="active" @selected(old('status', $integration->status) === 'active')>Active</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Type</label>
                                <input name="type" value="{{ old('type', $integration->type) }}" class="mt-1 w-full rounded-md border-gray-300" placeholder="facebook_pixel, google_analytics, …">
                                <x-input-error :messages="$errors->get('type')" class="mt-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700">Provider</label>
                                <input name="provider" value="{{ old('provider', $integration->provider) }}" class="mt-1 w-full rounded-md border-gray-300">
                                <x-input-error :messages="$errors->get('provider')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700">Settings (JSON)</label>
                            <textarea name="settings_text" rows="8" class="mt-1 w-full rounded-md border-gray-300" placeholder='{"key":"value"}'>{{ old('settings_text', $integration->settings_json ? json_encode($integration->settings_json, JSON_PRETTY_PRINT) : '') }}</textarea>
                            <x-input-error :messages="$errors->get('settings_text')" class="mt-2" />
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button class="rounded-md bg-indigo-600 px-6 py-2 text-sm text-white" type="submit">Save</button>
                        </div>
                    </form>

                    @if ($integration->exists)
                        <form method="post" action="{{ route('admin.integrations.destroy', $integration) }}" class="mt-4">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-md bg-red-600 px-6 py-2 text-sm text-white" type="submit" onclick="return confirm('Delete this integration?');">Delete</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
