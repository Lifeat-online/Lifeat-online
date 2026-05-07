@extends('layouts.public')

@section('title', 'Voucher Dashboard')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Voucher Dashboard · {{ $listing->title }}</h2>
                <p class="section-subtitle">Track redemptions, usage, and inventory.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.listings.vouchers.index', $listing) }}">Manage vouchers</a>
                <a class="button-link" href="{{ route('account.listings.show', $listing) }}">Back to listing</a>
            </div>
        </div>

        <div class="grid grid-3">
            <article class="card">
                <strong class="stat-number">{{ $totals['vouchers'] }}</strong>
                <div class="muted">Total vouchers</div>
            </article>
            <article class="card">
                <strong class="stat-number">{{ $totals['published'] }}</strong>
                <div class="muted">Published</div>
            </article>
            <article class="card">
                <strong class="stat-number">{{ $totals['claimed'] }}</strong>
                <div class="muted">Claimed</div>
            </article>
            <article class="card">
                <strong class="stat-number">{{ $totals['consumed'] }}</strong>
                <div class="muted">Used</div>
            </article>
        </div>
    </section>

    <section class="section">
        <div class="card">
            <h3 class="h3-block">Recent activity</h3>
            @forelse ($recentRedemptions as $redemption)
                <div style="padding:1rem 0; border-bottom:1px solid rgba(15, 23, 42, 0.08);">
                    <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                        <div>
                            <strong>{{ $redemption->voucher?->title ?: 'Voucher' }}</strong>
                            <div class="muted">{{ $redemption->created_at?->format('j M Y H:i') ?: '' }}</div>
                        </div>
                        <div style="text-align:right;">
                            <div><strong>{{ $redemption->code }}</strong></div>
                            <div class="muted">{{ ucfirst($redemption->status) }}</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty-state">No voucher activity yet.</div>
            @endforelse
        </div>
    </section>
@endsection

