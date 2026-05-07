@extends('layouts.public')

@section('title', 'Vouchers')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Vouchers</h2>
                <p class="section-subtitle">Browse limited-time offers from businesses in the directory.</p>
            </div>
        </div>

        <form method="get" action="{{ route('vouchers.index') }}" class="card">
            <div class="form-grid" style="grid-template-columns: 2fr 1fr 1fr auto;">
                <div>
                    <label for="q">Search</label>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search vouchers or businesses">
                </div>
                <div>
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id">
                        <option value="">All</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === (int) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sort">Sort</label>
                    <select id="sort" name="sort">
                        <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option>
                        <option value="ending" @selected(($filters['sort'] ?? '') === 'ending')>Ending soon</option>
                        <option value="popular" @selected(($filters['sort'] ?? '') === 'popular')>Most popular</option>
                    </select>
                </div>
                <div>
                    <button class="button" type="submit">Apply</button>
                </div>
            </div>
        </form>
    </section>

    <section class="section">
        <div class="grid grid-3">
            @forelse ($vouchers as $voucher)
                <article class="card">
                    <div class="meta">
                        <span>{{ $voucher->listing?->title ?: 'Business' }}</span>
                        @if ($voucher->end_at)
                            <span>Ends {{ $voucher->end_at->format('j M Y') }}</span>
                        @endif
                    </div>
                    <h3 class="h3-card">
                        <a href="{{ route('vouchers.show', [$voucher->listing, $voucher]) }}">{{ $voucher->title }}</a>
                    </h3>
                    <p class="muted">{{ \Illuminate\Support\Str::limit($voucher->description ?: '', 140) }}</p>
                    <div style="margin-top:0.75rem;">
                        @foreach ($voucher->categories->take(3) as $category)
                            <span class="badge">{{ $category->name }}</span>
                        @endforeach
                    </div>
                    <div style="display:flex; justify-content:space-between; gap:0.75rem; align-items:center; margin-top:1rem;">
                        <div>
                            @if ($voucher->formattedValue())
                                <strong>{{ $voucher->formattedValue() }}</strong>
                            @endif
                            <div class="muted" style="font-size:0.92rem;">{{ $voucher->remainingUses() }} left</div>
                        </div>
                        <a class="button" href="{{ route('vouchers.show', [$voucher->listing, $voucher]) }}">View</a>
                    </div>
                </article>
            @empty
                <div class="empty-state" style="grid-column: 1 / -1;">No vouchers found.</div>
            @endforelse
        </div>

        <div style="margin-top:1.25rem;">
            {{ $vouchers->links() }}
        </div>
    </section>
@endsection

