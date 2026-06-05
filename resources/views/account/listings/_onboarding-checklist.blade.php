@php
    $nextStep = $onboardingChecklist['next'] ?? null;
@endphp

<article class="card">
    <div class="section-head">
        <div>
            <h3>Listing Launch Checklist</h3>
            <p class="muted">{{ $onboardingChecklist['completed'] }} of {{ $onboardingChecklist['total'] }} steps complete.</p>
        </div>
        @if ($nextStep && $nextStep['action_url'])
            <a class="button" href="{{ $nextStep['action_url'] }}">{{ $nextStep['action_label'] }}</a>
        @endif
    </div>

    @if ($nextStep)
        <div class="empty-state" style="margin-bottom:1rem;">
            <strong>Next: {{ $nextStep['label'] }}</strong><br>
            {{ $nextStep['detail'] }}
        </div>
    @endif

    <div class="grid grid-2">
        @foreach ($onboardingChecklist['steps'] as $step)
            @php
                $tone = match ($step['status']) {
                    'done' => 'Done',
                    'next' => 'Next',
                    default => 'Pending',
                };
                $border = match ($step['status']) {
                    'done' => '#16a34a',
                    'next' => '#2563eb',
                    default => 'rgba(15, 23, 42, 0.18)',
                };
            @endphp
            <div style="border:1px solid {{ $border }}; border-radius:8px; padding:1rem;">
                <span class="badge">{{ $tone }}</span>
                <h4 style="margin:0.75rem 0 0.35rem;">{{ $step['label'] }}</h4>
                <p class="muted" style="margin-bottom:0.75rem;">{{ $step['detail'] }}</p>
                @if ($step['action_url'])
                    <a class="button-link" href="{{ $step['action_url'] }}">{{ $step['action_label'] }}</a>
                @endif
            </div>
        @endforeach
    </div>
</article>
