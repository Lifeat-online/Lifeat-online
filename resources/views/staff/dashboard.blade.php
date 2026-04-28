<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Staff Workspace</h2>
    </x-slot>

    <style>
        .dash-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
        }
        .mgmt-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 1.5rem;
            letter-spacing: -0.025em;
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
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 0;
            cursor: pointer;
        }
        .btn-slate {
            background: linear-gradient(135deg, #475569, #1e293b);
            color: #ffffff !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .btn-slate:hover { transform: translateY(-1px); filter: brightness(1.1); }
        .btn-indigo {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        .btn-indigo:hover { transform: translateY(-1px); filter: brightness(1.1); }
        .btn-soft {
            background: #f1f5f9;
            color: #475569 !important;
            border: 1px solid #e2e8f0;
        }
        .btn-soft:hover { background: #e2e8f0; }

        .stat-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        .stat-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 14px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-label { color: #64748b; font-size: 0.875rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; }
        .stat-value { color: #0f172a; font-size: 1.875rem; font-weight: 800; margin-top: 0.5rem; }

        .recent-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }
        .section-header { font-weight: 700; color: #475569; margin-bottom: 1rem; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .item-link { color: #4f46e5; font-weight: 700; text-decoration: none; font-size: 0.95rem; }
        .item-link:hover { text-decoration: underline; }
        .item-meta { color: #94a3b8; font-size: 0.8rem; margin-top: 0.25rem; }

        html[data-theme="dark"] .dash-card,
        html[data-theme="dark"] .stat-card {
            background: #111827;
            border-color: #1f2937;
        }
        html[data-theme="dark"] .mgmt-title { color: #f8fafc; }
        html[data-theme="dark"] .stat-value { color: #f8fafc; }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <div class="dash-card">
                <h3 class="mgmt-title">Workplace Tools</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    <a href="{{ route('writer.articles.create') }}" class="btn-mgmt btn-indigo">New Article</a>
                    <a href="{{ route('add-listing.index') }}" class="btn-mgmt btn-slate">New Listing</a>
                    <a href="{{ route('account.listings.index') }}" class="btn-mgmt btn-slate">New Event</a>
                    <a href="{{ route('writer.articles.index') }}" class="btn-mgmt btn-soft">My Articles</a>
                    <a href="{{ route('writer.earnings.index') }}" class="btn-mgmt btn-soft">Earnings Report</a>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-card"><p class="stat-label">My Articles</p><p class="stat-value">{{ $counts['articles'] }}</p></div>
                <div class="stat-card"><p class="stat-label">My Listings</p><p class="stat-value">{{ $counts['listings'] }}</p></div>
                <div class="stat-card"><p class="stat-label">Pending Pay</p><p class="stat-value" style="color: #059669;">ZAR {{ number_format($earnings['pending'], 2) }}</p></div>
                <div class="stat-card"><p class="stat-label">Paid To Date</p><p class="stat-value">ZAR {{ number_format($earnings['paid'], 2) }}</p></div>
            </div>

            <div class="dash-card">
                <div class="recent-grid">
                    <div>
                        <h4 class="section-header">Recent Articles</h4>
                        @foreach ($latest['articles'] as $item)
                            <div style="padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
                                <a href="{{ route('writer.articles.edit', $item) }}" class="item-link">{{ $item->title }}</a>
                                <p class="item-meta">{{ ucfirst($item->status) }} · {{ $item->created_at->diffForHumans() }}</p>
                            </div>
                        @endforeach
                    </div>
                    <div>
                        <h4 class="section-header">Recent Listings</h4>
                        @foreach ($latest['listings'] as $item)
                            <div style="padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;">
                                <a href="{{ route('account.listings.edit', $item) }}" class="item-link">{{ $item->title }}</a>
                                <p class="item-meta">{{ ucfirst($item->status) }} · {{ $item->created_at->diffForHumans() }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
