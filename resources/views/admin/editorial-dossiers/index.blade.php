<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Editorial evidence dossiers</h2>
            <p class="mt-1 text-sm text-gray-500">Review claim maps, contradictions, and source-backed fact checks before Jimmy writes.</p>
        </div>
    </x-slot>
    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <form class="flex gap-3" method="get">
                <select name="status" class="rounded-md border-gray-300 text-sm">
                    <option value="">All statuses</option>
                    @foreach (['draft', 'approved', 'rejected'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>@endforeach
                </select>
                <button class="rounded-md bg-slate-700 px-4 py-2 text-sm text-white">Filter</button>
            </form>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse ($dossiers as $dossier)
                    <a href="{{ route('admin.editorial-dossiers.show', $dossier) }}" class="rounded-xl bg-white p-5 shadow-sm hover:ring-2 hover:ring-indigo-200">
                        <div class="flex justify-between gap-3"><h3 class="font-semibold text-gray-900">{{ $dossier->title }}</h3><span class="text-xs uppercase text-gray-500">{{ $dossier->status }}</span></div>
                        <p class="mt-2 text-sm text-gray-500">{{ $dossier->cluster?->title }} · {{ $dossier->claims_count }} claims</p>
                    </a>
                @empty
                    <p class="text-sm text-gray-500">No dossiers match this filter.</p>
                @endforelse
            </div>
            {{ $dossiers->links() }}
        </div>
    </div>
</x-app-layout>
