@extends('layouts.public')

@section('title', 'Manage Vouchers')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Vouchers · {{ $listing->title }}</h2>
                <p class="section-subtitle">Create and manage promotional vouchers for your business listing.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.listings.show', $listing) }}">Back to listing</a>
                <a class="button-link" href="{{ route('account.listings.vouchers.dashboard', $listing) }}">Dashboard</a>
                <a class="button" href="{{ route('account.listings.vouchers.create', $listing) }}">Create voucher</a>
            </div>
        </div>

        @if (session('status'))
            <div class="card">{{ session('status') }}</div>
        @endif

        <form method="get" action="{{ route('account.listings.vouchers.index', $listing) }}" class="card">
            <div class="form-grid" style="grid-template-columns: 1fr auto;">
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All</option>
                        <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                        <option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option>
                    </select>
                </div>
                <div>
                    <button class="button" type="submit">Filter</button>
                </div>
            </div>
        </form>
    </section>

    <section class="section">
        <div class="card">
            @forelse ($vouchers as $voucher)
                <div style="padding:1rem 0; border-bottom:1px solid rgba(15, 23, 42, 0.08);">
                    <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                        <div>
                            <strong>{{ $voucher->title }}</strong>
                            <div class="muted">{{ ucfirst($voucher->status) }} · {{ $voucher->formattedValue() ?: 'Offer' }}</div>
                        </div>
                        <div style="text-align:right;">
                            <div><strong>{{ $voucher->remainingUses() }}</strong> left</div>
                            <div class="muted">{{ (int) $voucher->claimed_count }} claimed · {{ (int) $voucher->consumed_count }} used</div>
                        </div>
                    </div>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.75rem;">
                        <a class="button-link" href="{{ route('account.listings.vouchers.edit', [$listing, $voucher]) }}">Edit</a>
                        @if ($voucher->status === 'published' && $listing->status === 'published')
                            <a class="button-link" href="{{ route('vouchers.show', [$listing, $voucher]) }}">View public</a>
                        @endif
                        <form method="post" action="{{ route('account.listings.vouchers.destroy', [$listing, $voucher]) }}" onsubmit="return confirm('Remove this voucher?');">
                            @csrf
                            @method('DELETE')
                            <button class="button-link" type="submit">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty-state">No vouchers created yet.</div>
            @endforelse
        </div>

        <div style="margin-top:1.25rem;">
            {{ $vouchers->links() }}
        </div>
    </section>
@endsection
