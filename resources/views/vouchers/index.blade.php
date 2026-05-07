@extends('layouts.public')

@section('title', 'Vouchers')

@section('content')
    <section class="section">
        <div class="section-head" data-reveal>
            <div>
                <h1>Vouchers</h1>
                <p class="section-subtitle">Limited-time offers from businesses across the Eastern Freestate.</p>
            </div>
            <div class="meta">
                <span>{{ $vouchers->total() }} offers</span>
                <span><a href="{{ route('directory.index') }}">Browse businesses</a></span>
            </div>
        </div>

        <form method="get" action="{{ route('vouchers.index') }}" class="card" data-reveal>
            <div class="grid gap-4 md:grid-cols-4 items-end">
                <div class="md:col-span-2">
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
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-1">
                    <div>
                        <label for="sort">Sort</label>
                        <select id="sort" name="sort">
                            <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest</option>
                            <option value="ending" @selected(($filters['sort'] ?? '') === 'ending')>Ending soon</option>
                            <option value="popular" @selected(($filters['sort'] ?? '') === 'popular')>Most popular</option>
                        </select>
                    </div>
                    <div class="sm:self-end md:self-auto">
                        <button class="button w-full" type="submit">Apply filters</button>
                    </div>
                </div>
            </div>
        </form>
    </section>

    <section class="section">
        <div class="grid grid-3">
            @forelse ($vouchers as $voucher)
                <x-voucher-card :voucher="$voucher" />
            @empty
                <div class="empty-state" style="grid-column: 1 / -1;" data-reveal>No vouchers found.</div>
            @endforelse
        </div>

        <div class="mt-10" data-reveal>
            {{ $vouchers->links() }}
        </div>
    </section>
@endsection
