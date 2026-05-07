@extends('layouts.public')

@section('title', $voucher->title.' | Vouchers')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>{{ $voucher->title }}</h2>
                <div class="meta">
                    <span><a href="{{ route('directory.show', $listing) }}">{{ $listing->title }}</a></span>
                    @if ($voucher->start_at)
                        <span>Starts {{ $voucher->start_at->format('j M Y') }}</span>
                    @endif
                    @if ($voucher->end_at)
                        <span>Ends {{ $voucher->end_at->format('j M Y') }}</span>
                    @endif
                </div>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
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
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="card" style="border-color:#fca5a5;">
                <strong>Could not redeem voucher</strong>
                <div class="muted" style="margin-top:0.35rem;">
                    {{ collect($errors->all())->first() }}
                </div>
            </div>
        @endif

        <div class="grid grid-2">
            <article class="card">
                <h3 class="h3-block">Offer</h3>
                <p>{{ $voucher->description ?: 'Details coming soon.' }}</p>
                <div style="margin-top:0.75rem;">
                    @foreach ($voucher->categories as $category)
                        <span class="badge">{{ $category->name }}</span>
                    @endforeach
                </div>
            </article>

            <article class="card">
                <h3 class="h3-block">Availability</h3>
                <p><strong>Value:</strong> {{ $voucher->formattedValue() ?: 'Offer' }}</p>
                <p><strong>Remaining:</strong> {{ $voucher->remainingUses() }} of {{ $voucher->usage_limit }}</p>
                <p><strong>Status:</strong> {{ $isActive ? 'Active' : 'Unavailable' }}</p>
                @if ($voucher->terms)
                    <div style="margin-top:0.75rem;">
                        <strong>Terms</strong>
                        <div class="muted" style="margin-top:0.35rem;">{!! nl2br(e($voucher->terms)) !!}</div>
                    </div>
                @endif
            </article>
        </div>
    </section>
@endsection

