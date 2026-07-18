<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.editorial-dossiers.index') }}" class="text-sm text-indigo-600">← Evidence dossiers</a>
                <h2 class="mt-1 text-xl font-semibold text-gray-800">{{ $dossier->title }}</h2>
                <p class="mt-1 text-sm text-gray-500">Claim map and fact-check workspace for {{ $dossier->cluster->title }}.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium uppercase text-slate-700">{{ $dossier->status }}</span>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<div class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="rounded-lg bg-red-50 p-4 text-sm text-red-700">{{ implode(' ', $errors->all()) }}</div>@endif

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">Approval gate</h3>
                        <p class="mt-1 text-sm text-gray-500">Every high-importance claim must have at least one supporting snapshot.</p>
                    </div>
                    <form method="post" action="{{ route('admin.editorial-dossiers.approve', $dossier) }}">@csrf
                        <button class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Approve dossier</button>
                    </form>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900">Claim map</h3>
                <div class="mt-4 space-y-5">
                    @forelse ($dossier->claims as $claim)
                        @php($hasChallenge = $claim->evidence->contains('stance', 'challenges'))
                        <article class="rounded-xl bg-white p-6 shadow-sm">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap gap-2 text-xs uppercase tracking-wide">
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-600">{{ $claim->importance }}</span>
                                        <span class="rounded-full bg-indigo-50 px-2 py-1 text-indigo-700">{{ $claim->status }}</span>
                                        @if ($hasChallenge)<span class="rounded-full bg-amber-50 px-2 py-1 text-amber-700">Contradiction flagged</span>@endif
                                    </div>
                                    <p class="mt-3 font-medium text-gray-900">{{ $claim->claim }}</p>
                                </div>
                                <form method="post" action="{{ route('admin.editorial-dossiers.claims.update', [$dossier, $claim]) }}" class="flex flex-wrap gap-2">@csrf @method('put')
                                    <select name="importance" class="rounded-md border-gray-300 text-sm">@foreach (['low','medium','high'] as $value)<option @selected($claim->importance === $value)>{{ $value }}</option>@endforeach</select>
                                    <select name="status" class="rounded-md border-gray-300 text-sm">@foreach (['unverified','verified','disputed','rejected'] as $value)<option @selected($claim->status === $value)>{{ $value }}</option>@endforeach</select>
                                    <button class="rounded-md bg-slate-700 px-3 py-2 text-sm text-white">Save fact check</button>
                                </form>
                            </div>

                            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-800">Evidence and contradictions</h4>
                                    <div class="mt-2 space-y-2">
                                        @forelse ($claim->evidence as $evidence)
                                            <div class="rounded-lg border p-3 text-sm">
                                                <div class="flex justify-between gap-3"><strong>{{ ucfirst($evidence->stance) }}</strong><span>Authority {{ $evidence->authority_score }}/100</span></div>
                                                <p class="mt-2 text-gray-600">{{ $evidence->excerpt ?: \Illuminate\Support\Str::limit($evidence->snapshot->content, 260) }}</p>
                                                <a href="{{ $evidence->snapshot->url }}" target="_blank" rel="noopener" class="mt-2 inline-block text-indigo-600">Open immutable source</a>
                                            </div>
                                        @empty
                                            <p class="text-sm text-gray-500">No evidence attached.</p>
                                        @endforelse
                                    </div>
                                </div>
                                <form method="post" action="{{ route('admin.editorial-dossiers.evidence.store', [$dossier, $claim]) }}" class="rounded-lg bg-slate-50 p-4">@csrf
                                    <h4 class="text-sm font-semibold text-gray-800">Attach source evidence</h4>
                                    <select name="source_snapshot_id" class="mt-3 w-full rounded-md border-gray-300 text-sm" required>
                                        @foreach ($snapshots as $snapshot)<option value="{{ $snapshot->id }}">{{ $snapshot->researchItem->title }} · {{ $snapshot->fetched_at?->format('Y-m-d H:i') }}</option>@endforeach
                                    </select>
                                    <div class="mt-3 grid grid-cols-2 gap-3">
                                        <select name="stance" class="rounded-md border-gray-300 text-sm">@foreach (['supports','challenges','context'] as $value)<option>{{ $value }}</option>@endforeach</select>
                                        <input type="number" min="0" max="100" name="authority_score" value="50" class="rounded-md border-gray-300 text-sm" aria-label="Authority score">
                                    </div>
                                    <textarea name="excerpt" rows="3" class="mt-3 w-full rounded-md border-gray-300 text-sm" placeholder="Exact supporting or challenging excerpt"></textarea>
                                    <button class="mt-3 rounded-md bg-indigo-600 px-3 py-2 text-sm text-white">Attach evidence</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <p class="rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm">No claims have been extracted for this dossier.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-900">Immutable source snapshots</h3>
                <div class="mt-3 space-y-3">
                    @foreach ($snapshots as $snapshot)
                        <details class="rounded-lg border p-3">
                            <summary class="cursor-pointer text-sm font-medium text-gray-800">{{ $snapshot->researchItem->title }} · {{ $snapshot->url }}</summary>
                            <p class="mt-3 whitespace-pre-wrap text-sm text-gray-600">{{ \Illuminate\Support\Str::limit($snapshot->content, 2000) }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
