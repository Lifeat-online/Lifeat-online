@php
    $locales = config('localization.supported', []);
    $currentLocale = app()->getLocale();
@endphp

<div class="inline-flex items-center gap-1" aria-label="Language selector">
    @foreach ($locales as $locale => $details)
        <form method="post" action="{{ route('locale.switch', $locale) }}">
            @csrf
            <button
                type="submit"
                class="{{ $currentLocale === $locale ? 'ring-2 ring-indigo-500' : '' }} inline-flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 bg-white text-lg shadow-sm transition hover:bg-gray-50"
                title="{{ $details['name'] ?? strtoupper($locale) }}"
                aria-label="Switch to {{ $details['name'] ?? strtoupper($locale) }}"
            >
                <span aria-hidden="true">{{ $details['flag'] ?? strtoupper($locale) }}</span>
            </button>
        </form>
    @endforeach
</div>
