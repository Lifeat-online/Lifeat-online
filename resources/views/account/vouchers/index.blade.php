@extends('layouts.public')

@section('title', 'My Vouchers')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>My Vouchers</h2>
                <p class="section-subtitle">Your redeemed vouchers and codes.</p>
            </div>
            <a class="button-link" href="{{ route('account.index') }}">Back to account</a>
        </div>

        @if (session('status'))
            <div class="card">{{ session('status') }}</div>
        @endif

        <div class="card">
            @forelse ($redemptions as $redemption)
                <div style="padding:1rem 0; border-bottom:1px solid rgba(15, 23, 42, 0.08);">
                    <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                        <div>
                            <strong>{{ $redemption->voucher?->title ?: 'Voucher' }}</strong>
                            <div class="muted">{{ $redemption->voucher?->listing?->title ?: 'Business' }}</div>
                        </div>
                        <div style="text-align:right;">
                            <div><strong>{{ $redemption->code }}</strong></div>
                            <div class="muted">{{ ucfirst($redemption->status) }}{{ $redemption->consumed_at ? ' · '.$redemption->consumed_at->format('j M Y H:i') : '' }}</div>
                        </div>
                    </div>
                    @if ($redemption->voucher && $redemption->voucher->listing)
                        <div class="muted" style="margin-top:0.5rem;">
                            <a href="{{ route('vouchers.show', [$redemption->voucher->listing, $redemption->voucher]) }}">View voucher</a>
                        </div>
                    @endif
                </div>
            @empty
                <div class="empty-state">No vouchers redeemed yet.</div>
            @endforelse
        </div>

        <div style="margin-top:1.25rem;">
            {{ $redemptions->links() }}
        </div>
    </section>
@endsection

