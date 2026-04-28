@props(['campaign'])

@php use Illuminate\Support\Facades\Storage; @endphp

{{--
    Ad Campaign Card — renders a sponsored listing card with tracking baked in.
    Impression pixel fires when the browser loads the card.
    CTA link routes through the click-tracking redirect.
    Usage: <x-ad-campaign-card :campaign="$campaign" />
--}}
@if ($campaign && $campaign->status === 'active')
    <div style="border-radius:16px; border:1px solid var(--border, #e2e8f0); background:var(--surface, #fff); padding:1.2rem; position:relative;">
        <span style="display:inline-block; font-size:0.68rem; font-weight:600; letter-spacing:0.06em; text-transform:uppercase; color:var(--muted, #64748b); margin-bottom:0.6rem;">Sponsored</span>

        @if ($campaign->creative_image)
            <a href="{{ route('ad-tracking.click', $campaign) }}" aria-label="View {{ $campaign->title }} campaign">
                <img src="{{ Storage::disk('public')->url($campaign->creative_image) }}"
                     alt="{{ $campaign->headline ?? $campaign->title }}"
                     style="width:100%; border-radius:10px; object-fit:cover; max-height:160px; display:block; margin-bottom:0.75rem;">
            </a>
        @endif

        @if ($campaign->headline)
            <p style="font-weight:600; font-size:0.95rem; margin:0 0 0.35rem;">{{ $campaign->headline }}</p>
        @endif

        @if ($campaign->body)
            <p style="font-size:0.85rem; color:var(--muted, #64748b); margin:0 0 0.75rem; line-height:1.5;">{{ Str::limit($campaign->body, 100) }}</p>
        @endif

        <a href="{{ route('ad-tracking.click', $campaign) }}"
           style="display:inline-block; font-size:0.82rem; font-weight:600; color:var(--accent, #1d4ed8); text-decoration:underline;">
            {{ $campaign->listing?->title ?? 'Learn more' }} →
        </a>

        {{-- Impression tracking pixel — fires on card render --}}
        <img src="{{ route('ad-tracking.impression', $campaign) }}"
             width="1" height="1"
             alt=""
             aria-hidden="true"
             style="position:absolute; opacity:0; pointer-events:none; top:0; left:0;">
    </div>
@endif
