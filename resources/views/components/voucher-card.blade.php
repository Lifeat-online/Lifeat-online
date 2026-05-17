@props(['voucher'])

@php
    $listing = $voucher->listing;
    $isActive = $voucher->isCurrentlyActive() && $listing && $listing->status === 'published';
    $value = $voucher->formattedValue();
@endphp

<article {{ $attributes->merge(['class' => 'card group']) }} data-reveal>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="meta">
                <span class="truncate">
                    @if ($listing)
                        <a href="{{ route('directory.show', $listing) }}">{{ $listing->localizedValue('title') }}</a>
                    @else
                        Business
                    @endif
                </span>
                @if ($voucher->end_at)
                    <span>Ends {{ $voucher->end_at->format('j M Y') }}</span>
                @endif
            </div>
            <h3 class="h3-card">
                @if ($listing)
                    <a href="{{ route('vouchers.show', [$listing, $voucher]) }}">{{ $voucher->localizedValue('title') }}</a>
                @else
                    {{ $voucher->localizedValue('title') }}
                @endif
            </h3>
        </div>
        <div class="shrink-0 text-right">
            @if ($value)
                <div class="badge">{{ $value }}</div>
            @endif
            <div class="text-sm muted mt-05">{{ $voucher->remainingUses() }} left</div>
        </div>
    </div>

    @if ($voucher->localizedValue('description'))
        <p class="muted mt-08">{{ \Illuminate\Support\Str::limit($voucher->localizedValue('description'), 150) }}</p>
    @endif

    <div class="mt-10 flex flex-wrap gap-2">
        @foreach ($voucher->categories->take(3) as $category)
            <span class="badge">{{ $category->localizedValue('name') }}</span>
        @endforeach
        @if (! $isActive)
            <span class="badge" style="opacity:0.72;">Unavailable</span>
        @endif
    </div>

    @if ($listing)
        <div class="mt-10 flex items-center justify-between gap-3">
            @if ($listing->logo_path)
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::url($listing->logo_path) }}"
                    alt="{{ $listing->localizedValue('title') }} logo"
                    loading="lazy"
                    decoding="async"
                    class="w-10 h-10 rounded-xl border"
                    style="background:#fff; object-fit:contain; padding:0.35rem;"
                >
            @else
                <div class="w-10 h-10 rounded-xl border grid place-items-center muted" aria-hidden="true">
                    <x-icon name="sparkles" class="w-5 h-5" />
                </div>
            @endif
            <a class="button-link" href="{{ route('vouchers.show', [$listing, $voucher]) }}">
                View voucher <x-icon name="arrow-right" class="w-4 h-4" />
            </a>
        </div>
    @endif
</article>
