<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Classified Moderation</h2>
            <a href="{{ route('classifieds.index') }}" class="btn-mgmt btn-soft">View Public Classifieds</a>
        </div>
    </x-slot>

    <style>
        .dash-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }
        .btn-mgmt {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 0;
            cursor: pointer;
        }
        .btn-slate {
            background: linear-gradient(135deg, #475569, #1e293b);
            color: #ffffff !important;
        }
        .btn-indigo {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #ffffff !important;
        }
        .btn-soft {
            background: #f1f5f9;
            color: #475569 !important;
            border: 1px solid #e2e8f0;
        }
        .premium-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .premium-table th { padding: 1rem; background: #f8fafc; color: #64748b; font-weight: 700; text-align: left; font-size: 0.75rem; text-transform: uppercase; }
        .premium-table td { padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .premium-table tr:hover td { background: #fdfdfd; }
        
        .item-link { color: #4f46e5; font-weight: 700; text-decoration: none; font-size: 1rem; }
        .item-link:hover { text-decoration: underline; }
        .item-subtext { color: #64748b; font-size: 0.85rem; margin-top: 0.125rem; }
        
        .status-badge {
            display: inline-flex;
            padding: 0.25rem 0.625rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            background: #f1f5f9;
            color: #475569;
        }
        .status-pending { background: #fef9c3; color: #854d0e; }
        .status-published { background: #dcfce7; color: #166534; }
        .status-flagged { background: #fee2e2; color: #991b1b; }

        html[data-theme="dark"] .dash-card { background: #111827; border-color: #334155; }
        html[data-theme="dark"] .premium-table th { background: #1e293b; color: #94a3b8; }
        html[data-theme="dark"] .premium-table td { color: #e5eefb; border-color: #1f2937; }
        html[data-theme="dark"] .btn-soft {
            background: #1e293b;
            color: #f1f5f9 !important;
            border-color: #334155;
        }
        html[data-theme="dark"] select {
            background: #0f172a !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="dash-card">
                <form method="get" action="{{ route('admin.classifieds.index') }}">
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <span style="font-weight: 600; color: #64748b; font-size: 0.875rem;">Moderation Filter:</span>
                        <select style="border-radius: 10px; border: 1px solid #e2e8f0; padding: 0.5rem 2rem 0.5rem 1rem; font-size: 0.875rem;" name="status">
                            @foreach (['pending', 'published', 'hidden', 'flagged', 'rejected', 'all'] as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? 'pending') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                        <button class="btn-mgmt btn-indigo" type="submit">Apply Filter</button>
                    </div>
                </form>
            </div>

            <div class="dash-card">
                <div class="overflow-x-auto">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>Classified Item</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Reviewed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($classifieds as $classified)
                                <tr>
                                    <td>
                                        <a class="item-link" href="{{ route('admin.classifieds.show', $classified) }}">{{ $classified->title }}</a>
                                        <div class="item-subtext">{{ $classified->city ?: 'No location' }}</div>
                                    </td>
                                    <td style="font-weight: 500;">{{ $classified->user?->name ?: 'Guest' }}</td>
                                    <td>
                                        <span class="status-badge status-{{ $classified->status }}">
                                            {{ ucfirst($classified->status) }}
                                        </span>
                                    </td>
                                    <td style="font-size: 0.85rem; color: #64748b;">{{ optional($classified->submitted_at)->format('j M Y H:i') ?: '-' }}</td>
                                    <td style="font-size: 0.85rem; color: #64748b;">{{ optional($classified->reviewed_at)->format('j M Y H:i') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding: 3rem; text-align: center; color: #94a3b8;">No classifieds in this moderation state.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">{{ $classifieds->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
