@php
    $nextStep = $writerOnboarding['next'] ?? null;
@endphp

<div class="rounded-lg bg-white p-6 shadow-sm">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Writer Onboarding Checklist</h3>
            <p class="mt-1 text-sm text-gray-500">{{ $writerOnboarding['completed'] }} of {{ $writerOnboarding['total'] }} steps complete.</p>
        </div>
        @if ($nextStep && $nextStep['action_url'])
            <a href="{{ $nextStep['action_url'] }}" class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white">{{ $nextStep['action_label'] }}</a>
        @endif
    </div>

    @if ($nextStep)
        <div class="mt-4 rounded-md bg-indigo-50 p-4 text-sm text-indigo-950">
            <strong>Next: {{ $nextStep['label'] }}</strong>
            <p class="mt-1">{{ $nextStep['detail'] }}</p>
        </div>
    @endif

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach ($writerOnboarding['steps'] as $step)
            @php
                $classes = match ($step['status']) {
                    'done' => 'border-green-200 bg-green-50 text-green-800',
                    'next' => 'border-indigo-200 bg-indigo-50 text-indigo-800',
                    default => 'border-gray-200 bg-gray-50 text-gray-600',
                };
                $label = match ($step['status']) {
                    'done' => 'Done',
                    'next' => 'Next',
                    default => 'Pending',
                };
            @endphp
            <div class="rounded-lg border p-4 {{ $classes }}">
                <span class="text-xs font-semibold uppercase tracking-wide">{{ $label }}</span>
                <h4 class="mt-2 font-semibold">{{ $step['label'] }}</h4>
                <p class="mt-1 text-sm">{{ $step['detail'] }}</p>
                @if ($step['action_url'])
                    <a href="{{ $step['action_url'] }}" class="mt-3 inline-block text-sm font-semibold underline">{{ $step['action_label'] }}</a>
                @endif
            </div>
        @endforeach
    </div>
</div>
