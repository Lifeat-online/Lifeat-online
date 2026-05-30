@php
    $locales = config('localization.supported', []);
    $currentLocale = app()->getLocale();
@endphp

<div class="language-switcher" aria-label="Language selector">
    @foreach ($locales as $locale => $details)
        @php
            $isCurrent = $currentLocale === $locale;
            $label = $details['name'] ?? strtoupper($locale);
            $native = $details['native'] ?? $label;
        @endphp
        <form method="post" action="{{ route('locale.switch', $locale) }}" class="language-switcher__form">
            @csrf
            <button
                type="submit"
                class="language-switcher__button {{ $isCurrent ? 'is-active' : '' }}"
                title="{{ $isCurrent ? $label.' selected' : 'Switch to '.$label }}"
                aria-label="{{ $isCurrent ? $label.' selected' : 'Switch to '.$label }}"
                aria-pressed="{{ $isCurrent ? 'true' : 'false' }}"
            >
                <span class="language-switcher__flag-wrap" aria-hidden="true">
                    <span class="language-switcher__flag language-switcher__flag--{{ $locale }}"></span>
                </span>
                <span class="language-switcher__code" aria-hidden="true">{{ strtoupper($locale) }}</span>
                <span class="sr-only">{{ $native }}</span>
            </button>
        </form>
    @endforeach
</div>
