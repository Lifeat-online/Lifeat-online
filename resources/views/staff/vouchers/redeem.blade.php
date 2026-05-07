@extends('layouts.public')

@section('title', 'Use Voucher')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Use Voucher</h2>
                <p class="section-subtitle">Enter a voucher code to validate and consume it.</p>
            </div>
            <a class="button-link" href="{{ route('staff.dashboard') }}">Back to staff dashboard</a>
        </div>

        @if (session('status'))
            <div class="card">{{ session('status') }}</div>
        @endif

        <form method="get" action="{{ route('staff.vouchers.redeem') }}" class="card">
            <div class="form-grid" style="grid-template-columns: 1fr auto;">
                <div>
                    <label for="code">Voucher code</label>
                    <input id="code" name="code" value="{{ $code }}" placeholder="E.g. ABCD1234EF">
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <div>
                    <button class="button" type="submit">Validate</button>
                </div>
            </div>
        </form>
    </section>

    @if ($redemption)
        <section class="section">
            <div class="grid grid-2">
                <article class="card">
                    <h3 class="h3-block">Voucher</h3>
                    <p><strong>{{ $redemption->voucher->title }}</strong></p>
                    <p class="muted">{{ $redemption->voucher->listing->title }}</p>
                    <p>{{ $redemption->voucher->description }}</p>
                    <p class="muted">Status: {{ ucfirst($redemption->status) }}</p>
                </article>
                <article class="card">
                    <h3 class="h3-block">Use now</h3>
                    <p><strong>Code:</strong> {{ $redemption->code }}</p>
                    @if ($redemption->customer)
                        <p><strong>Customer:</strong> {{ $redemption->customer->name }} ({{ $redemption->customer->email }})</p>
                    @endif
                    <p><strong>Claimed:</strong> {{ optional($redemption->claimed_at)->format('j M Y H:i') ?: '-' }}</p>
                    <p><strong>Used:</strong> {{ optional($redemption->consumed_at)->format('j M Y H:i') ?: '-' }}</p>
                    <form method="post" action="{{ route('staff.vouchers.consume') }}" style="margin-top:0.75rem;">
                        @csrf
                        <input type="hidden" name="code" value="{{ $redemption->code }}">
                        <button class="button" type="submit" @disabled($redemption->status !== 'claimed')>Use now</button>
                    </form>
                </article>
            </div>
        </section>
    @endif
@endsection

