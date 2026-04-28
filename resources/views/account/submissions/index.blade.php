@extends('layouts.public')

@section('title', 'Submission History | Life Platform')

@section('content')
    <section class="section">
        <div class="section-head">
            <div>
                <h2>Submission History</h2>
                <p class="muted">Track statuses and feedback across your listings, classifieds, and article submissions in one place.</p>
            </div>
            <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                <a class="button-link" href="{{ route('account.index') }}">Back to account</a>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="stats">
            <div class="card">
                <div class="stat-number">{{ $submissionCounts['total'] }}</div>
                <div>Total submissions</div>
            </div>
            <div class="card">
                <div class="stat-number">{{ $submissionCounts['pending'] }}</div>
                <div>Needs attention</div>
            </div>
            <div class="card">
                <div class="stat-number">{{ $submissionCounts['published'] }}</div>
                <div>Published</div>
            </div>
        </div>
    </section>

    <section class="section">
        <form method="get" class="card form-grid">
            <div>
                <label for="type">Type</label>
                <select id="type" name="type">
                    <option value="">All types</option>
                    @foreach (['listing', 'classified', 'article'] as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Status</label>
                <input id="status" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="published, pending, draft...">
            </div>
            <div>
                <button class="button" type="submit">Filter</button>
                <a class="button-link" href="{{ route('account.submissions.index') }}">Reset</a>
            </div>
        </form>
    </section>

    <section class="section">
        @forelse ($submissions as $submission)
            <article class="card" style="margin-bottom:1rem;">
                <div class="section-head" style="margin-bottom:0.75rem;">
                    <div>
                        <span class="badge">{{ ucfirst($submission['type']) }}</span>
                        <h3 style="margin-top:0.5rem;">{{ $submission['title'] }}</h3>
                        <p class="muted">{{ str_replace('_', ' ', ucfirst($submission['status'])) }}@if($submission['location']) · {{ $submission['location'] }} @endif</p>
                    </div>
                    <div class="muted">{{ optional($submission['timestamp'])->format('j M Y') ?: '-' }}</div>
                </div>
                <p class="muted" style="margin-bottom:0.75rem;">{{ $submission['feedback'] }}</p>
                @if ($submission['action_url'])
                    <a class="button-link" href="{{ $submission['action_url'] }}">{{ $submission['action_label'] }}</a>
                @endif
            </article>
        @empty
            <div class="empty-state">No submissions match your current filters.</div>
        @endforelse

        <div style="margin-top: 1rem;">{{ $submissions->links() }}</div>
    </section>
@endsection
