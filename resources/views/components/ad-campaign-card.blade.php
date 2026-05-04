@props(['campaign'])

@php use Illuminate\Support\Facades\Storage; @endphp

{{--
    Ad Campaign Card — renders a sponsored listing card with tracking baked in.
    Impression pixel fires when the browser loads the card.
    CTA link routes through the click-tracking redirect.
    Usage: <x-ad-campaign-card :campaign="$campaign" />
--}}
@if ($campaign && $campaign->status === 'active')
    <div class="ad-card" style="border-radius:24px; border:1px solid var(--border); background: var(--surface); padding:1.4rem; position:relative; overflow:hidden; transition: transform 0.2s ease, box-shadow 0.2s ease; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
        <style>
            .ad-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            }
            .ad-badge {
                display: inline-flex;
                align-items: center;
                font-size: 0.65rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--primary-dark);
                background: rgba(29, 78, 216, 0.1);
                padding: 0.25rem 0.6rem;
                border-radius: 999px;
                margin-bottom: 0.8rem;
            }
            html[data-theme="dark"] .ad-badge {
                background: rgba(96, 165, 250, 0.15);
                color: #60a5fa;
            }
            .ad-image-container {
                position: relative;
                border-radius: 16px;
                overflow: hidden;
                margin-bottom: 1rem;
                aspect-ratio: 16 / 9;
                background: #f1f5f9;
            }
            html[data-theme="dark"] .ad-image-container {
                background: #1e293b;
            }
            .ad-image {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
                transition: transform 0.4s ease;
            }
            .ad-card:hover .ad-image {
                transform: scale(1.05);
            }
            .ad-cta {
                display: inline-flex;
                align-items: center;
                font-size: 0.88rem;
                font-weight: 700;
                color: var(--primary-dark);
                text-decoration: none;
                gap: 0.4rem;
                margin-top: 0.5rem;
            }
            .ad-cta:hover {
                text-decoration: underline;
            }
        </style>

        <div class="ad-badge">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:0.75rem; height:0.75rem; margin-right:0.3rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345l2.125-5.111Z" />
            </svg>
            Sponsored
        </div>

        @if ($campaign->creative_image)
            <div class="ad-image-container">
                <a href="{{ route('ad-tracking.click', $campaign) }}" aria-label="View {{ $campaign->title }} campaign">
                    <img src="{{ Storage::disk('public')->url($campaign->creative_image) }}"
                         alt="{{ $campaign->headline ?? $campaign->title }}"
                         class="ad-image">
                </a>
            </div>
        @endif

        <div style="margin-bottom: 1rem;">
            @if ($campaign->headline)
                <h4 style="font-weight:700; font-size:1.1rem; margin:0 0 0.4rem; color:var(--text); line-height:1.3;">{{ $campaign->headline }}</h4>
            @endif

            @if ($campaign->body)
                <p style="font-size:0.9rem; color:var(--muted); margin:0; line-height:1.5;">{{ Str::limit($campaign->body, 120) }}</p>
            @endif
        </div>

        <a href="{{ route('ad-tracking.click', $campaign) }}" class="ad-cta">
            <span>{{ $campaign->listing?->title ?? 'Learn more' }}</span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:1rem; height:1rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
            </svg>
        </a>

        {{-- Impression tracking pixel — fires on card render --}}
        <img src="{{ route('ad-tracking.impression', $campaign) }}"
             width="1" height="1"
             alt=""
             aria-hidden="true"
             style="position:absolute; opacity:0; pointer-events:none; top:0; left:0;">
    </div>
@endif
