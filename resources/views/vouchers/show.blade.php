@extends('layouts.public')

@section('title', $voucher->localizedValue('title').' | Vouchers')

@section('content')
    <section class="section">
        <div class="section-head" data-reveal>
            <div>
                <div class="eyebrow">Voucher offer</div>
                <h1>{{ $voucher->localizedValue('title') }}</h1>
                <div class="meta">
                    <span><a href="{{ route('directory.show', $listing) }}">{{ $listing->localizedValue('title') }}</a></span>
                    @if ($voucher->start_at)
                        <span>Starts {{ $voucher->start_at->format('j M Y') }}</span>
                    @endif
                    @if ($voucher->end_at)
                        <span>Ends {{ $voucher->end_at->format('j M Y') }}</span>
                    @endif
                    <span>{{ $voucher->remainingUses() }} of {{ $voucher->usage_limit }} remaining</span>
                </div>
            </div>
            <div class="flex gap-3 flex-wrap items-center">
                <a class="button-link" href="{{ route('vouchers.index') }}">Back to vouchers</a>
                @if ($isActive)
                    @auth
                        <form method="post" action="{{ route('vouchers.redeem', [$listing, $voucher]) }}">
                            @csrf
                            <button class="button" type="submit">Redeem</button>
                        </form>
                    @else
                        <a class="button" href="{{ route('login') }}">Login to redeem</a>
                    @endauth
                @else
                    <span class="badge" style="opacity:0.72;">Unavailable</span>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="card" style="border-color:#fca5a5;" data-reveal>
                <strong>Could not redeem voucher</strong>
                <div class="muted" style="margin-top:0.35rem;">
                    {{ collect($errors->all())->first() }}
                </div>
            </div>
        @endif

        <div class="grid grid-2">
            <article class="card" data-reveal>
                <h3 class="h3-block">Offer</h3>
                <p>{{ $voucher->localizedValue('description') ?: 'Details coming soon.' }}</p>
                <div style="margin-top:0.75rem;">
                    @foreach ($voucher->categories as $category)
                        <span class="badge">{{ $category->localizedValue('name') }}</span>
                    @endforeach
                </div>
            </article>

            <article class="card" data-reveal>
                <h3 class="h3-block">Availability</h3>
                <p><strong>Value:</strong> {{ $voucher->formattedValue() ?: 'Offer' }}</p>
                <p><strong>Remaining:</strong> {{ $voucher->remainingUses() }} of {{ $voucher->usage_limit }}</p>
                <p><strong>Status:</strong> {{ $isActive ? 'Active' : 'Unavailable' }}</p>
                @if ($voucher->terms)
                    <div style="margin-top:0.75rem;">
                        <strong>Terms</strong>
                        <div class="muted" style="margin-top:0.35rem;">{!! nl2br(e($voucher->localizedValue('terms'))) !!}</div>
                    </div>
                @endif
            </article>
        </div>
    </section>
@endsection
