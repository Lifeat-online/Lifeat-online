@php use App\Models\WriterApplication; @endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Writer Applications</h2>
                <p class="mt-1 text-sm text-gray-500">Review incoming staff and writer applications, then move them into the right decision state.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg bg-white p-6 shadow-sm">
                <form method="get" class="flex flex-wrap items-end gap-4">
                    <div class="min-w-52">
                        <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select id="status" name="status" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">All statuses</option>
                            @foreach ($statusOptions as $status)
                                <option value="{{ $status }}" @selected($selectedStatus === $status)>
                                    {{ str_replace('_', ' ', ucfirst($status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-52">
                        <label for="contact" class="mb-1 block text-sm font-medium text-gray-700">Contact state</label>
                        <select id="contact" name="contact" class="w-full rounded-md border-gray-300 text-sm shadow-sm">
                            <option value="">All contact states</option>
                            <option value="needs_contact" @selected($selectedContact === 'needs_contact')>
                                Needs access email ({{ $contactCounts['needs_contact'] }})
                            </option>
                            <option value="recently_contacted" @selected($selectedContact === 'recently_contacted')>
                                Recently contacted ({{ $contactCounts['recently_contacted'] }})
                            </option>
                        </select>
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white">Filter</button>
                    @if ($selectedStatus !== '' || $selectedContact !== '')
                        <a href="{{ route('admin.writer-applications.index') }}" class="text-sm text-gray-600">Clear filters</a>
                    @endif
                </form>

                <div class="mt-4 flex flex-wrap gap-3 text-sm text-gray-600">
                    @foreach ($statusOptions as $status)
                        <a href="{{ route('admin.writer-applications.index', ['status' => $status]) }}"
                           class="rounded-full px-3 py-1 {{ $selectedStatus === $status ? 'bg-indigo-100 text-indigo-800 font-semibold' : 'bg-gray-100 hover:bg-gray-200' }}">
                            {{ str_replace('_', ' ', ucfirst($status)) }}: {{ $statusCounts[$status] ?? 0 }}
                        </a>
                    @endforeach
                    <a href="{{ route('admin.writer-applications.index', ['contact' => 'needs_contact']) }}"
                       class="rounded-full px-3 py-1 {{ $selectedContact === 'needs_contact' ? 'bg-amber-100 text-amber-800 font-semibold' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                        Needs access email: {{ $contactCounts['needs_contact'] }}
                    </a>
                    <a href="{{ route('admin.writer-applications.index', ['contact' => 'recently_contacted']) }}"
                       class="rounded-full px-3 py-1 {{ $selectedContact === 'recently_contacted' ? 'bg-green-100 text-green-800 font-semibold' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                        Recently contacted: {{ $contactCounts['recently_contacted'] }}
                    </a>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left">Applicant</th>
                                    <th class="px-4 py-3 text-left">Contact</th>
                                    <th class="px-4 py-3 text-left">Linked User</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Contact State</th>
                                    <th class="px-4 py-3 text-left">Role</th>
                                    <th class="px-4 py-3 text-left">Access</th>
                                    <th class="px-4 py-3 text-left">Submitted</th>
                                    <th class="px-4 py-3 text-left">Reviewed</th>
                                    <th class="px-4 py-3 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($applications as $application)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="font-medium text-gray-900">{{ $application->fullName() }}</p>
                                            <p class="text-xs text-gray-500">{{ '@'.$application->username }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p>{{ $application->email }}</p>
                                            <p class="text-xs text-gray-500">{{ $application->phone }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ $application->user?->name ?: 'Guest applicant' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
                                                {{ str_replace('_', ' ', ucfirst($application->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($application->status === WriterApplication::STATUS_APPROVED && ! $application->access_summary['last_sent_at'])
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Needs access email</span>
                                            @elseif ($application->access_summary['last_sent_at'] && $application->access_summary['last_sent_at']->gte(now()->subDays(7)))
                                                <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Recently contacted</span>
                                            @elseif ($application->access_summary['last_sent_at'])
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Contacted</span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ $application->assigned_role ? str_replace('_', ' ', ucfirst($application->assigned_role)) : '-' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($application->access_summary['resend_available_at'] ?? null)
                                                <p class="text-xs font-medium text-amber-700">
                                                    Cooldown until {{ $application->access_summary['resend_available_at']->format('H:i') }}
                                                </p>
                                            @elseif ($application->access_summary['last_sent_at'] ?? null)
                                                <p class="text-xs font-medium text-gray-700">
                                                    Last sent {{ $application->access_summary['last_sent_at']->diffForHumans() }}
                                                </p>
                                            @else
                                                <p class="text-xs text-gray-500">No access email yet</p>
                                            @endif

                                            @if (($application->access_summary['event_count'] ?? 0) > 0)
                                                <p class="text-xs text-gray-500">
                                                    {{ $application->access_summary['event_count'] }} event{{ ($application->access_summary['event_count'] ?? 0) === 1 ? '' : 's' }}
                                                    @if ($application->access_summary['last_event_action'] ?? null)
                                                        • Last {{ $application->access_summary['last_event_action'] }}
                                                    @endif
                                                </p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ optional($application->submitted_at)->format('j M Y H:i') ?: '-' }}</td>
                                        <td class="px-4 py-3">{{ optional($application->reviewed_at)->format('j M Y H:i') ?: '-' }}</td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.writer-applications.show', $application) }}" class="text-indigo-600">Review</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-4 py-8 text-center text-gray-500">No applications match the current filter.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">{{ $applications->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
