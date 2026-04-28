<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Platform Settings</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                @if (session('status'))
                    <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('admin.settings.update') }}" class="space-y-8">
                    @csrf
                    @method('PUT')

                    @foreach ($groupedSettings as $group => $settings)
                        <section class="rounded-lg border border-gray-200 p-5">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">{{ ucwords(str_replace(['.', '_'], ' ', $group)) }}</h3>
                            <div class="grid gap-5 md:grid-cols-2">
                                @foreach ($settings as $setting)
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700" for="setting-{{ $setting->id }}">
                                            {{ $setting->key }}
                                        </label>
                                        <input
                                            id="setting-{{ $setting->id }}"
                                            name="settings[{{ $setting->key }}]"
                                            value="{{ old('settings.'.$setting->key, $setting->value) }}"
                                            class="w-full rounded-md border-gray-300"
                                        >
                                        <p class="mt-1 text-xs text-gray-500">Type: {{ $setting->type }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    <div class="flex gap-3">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-white" type="submit">Save Settings</button>
                        <a href="{{ route('admin.dashboard') }}" class="rounded-md bg-gray-200 px-4 py-2 text-gray-800">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
