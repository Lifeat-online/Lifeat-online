@php
    $locales = config('localization.supported', []);
    $currentLocale = app()->getLocale();
@endphp

<div class="language-switcher" aria-label="Language selector" role="group">
    @foreach ($locales as $locale => $details)
        @php
            $isCurrent = $currentLocale === $locale;
            $label = $details['name'] ?? strtoupper($locale);
            $native = $details['native'] ?? $label;
            $code = strtoupper($locale);
        @endphp
        <form method="post" action="{{ route('locale.switch', $locale) }}" class="language-switcher__form" data-locale-switch-form data-locale-name="{{ $native }}">
            @csrf
            <button
                type="submit"
                class="language-switcher__button {{ $isCurrent ? 'is-active' : '' }}"
                title="{{ $isCurrent ? $label.' selected' : 'Switch to '.$label }}"
                aria-label="{{ $isCurrent ? $label.' selected' : 'Switch to '.$label }}"
                aria-pressed="{{ $isCurrent ? 'true' : 'false' }}"
                data-locale-switch-button
            >
                <span class="language-switcher__badge language-switcher__badge--{{ $locale }}" aria-hidden="true">{{ $code }}</span>
                <span class="language-switcher__name" aria-hidden="true">{{ $native }}</span>
                <span class="sr-only">{{ $native }}</span>
            </button>
        </form>
    @endforeach
</div>
