@php
    $locales = config('localization.supported', []);
    $currentLocale = app()->getLocale();
@endphp

<div class="language-switcher" aria-label="Language selector">
    @foreach ($locales as $locale => $details)
        <form method="post" action="{{ route('locale.switch', $locale) }}">
            @csrf
            <button
                type="submit"
                class="{{ $currentLocale === $locale ? 'ring-2 ring-indigo-500' : '' }} language-switcher__button"
                title="{{ $details['name'] ?? strtoupper($locale) }}"
                aria-label="Switch to {{ $details['name'] ?? strtoupper($locale) }}"
            >
                <span class="language-switcher__flag language-switcher__flag--{{ $locale }}" aria-hidden="true"></span>
                <span class="sr-only">{{ $details['native'] ?? $details['name'] ?? strtoupper($locale) }}</span>
            </button>
        </form>
    @endforeach
</div>
