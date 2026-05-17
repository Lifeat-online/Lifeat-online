<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
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
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 0;
            cursor: pointer;
        }
        .btn-slate {
            background: linear-gradient(135deg, #334155, #1e293b);
            color: #ffffff !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .btn-slate:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            filter: brightness(1.1);
        }
        .btn-indigo {
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        .btn-indigo:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
            filter: brightness(1.1);
        }
        .stat-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            margin: 2rem 0;
        }
        .stat-card {
            background: #ffffff;
            padding: 1.5rem;
            border-radius: 14px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-label { color: #64748b; font-size: 0.875rem; font-weight: 500; }
        .stat-value { color: #0f172a; font-size: 1.875rem; font-weight: 800; margin-top: 0.5rem; }
        
        .recent-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }
        .recent-section h4 {
            font-weight: 700;
            color: #475569;
            margin-bottom: 1rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .recent-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .recent-item:last-child { border: 0; }
        .recent-link { color: #4f46e5; font-weight: 600; text-decoration: none; font-size: 0.95rem; }
        .recent-link:hover { text-decoration: underline; }
        .recent-meta { color: #94a3b8; font-size: 0.8rem; margin-top: 0.25rem; }

        html[data-theme="dark"] .dash-card,
        html[data-theme="dark"] .stat-card {
            background: #1e293b;
            border-color: #334155;
        }
        html[data-theme="dark"] .mgmt-title { color: #f8fafc; }
        html[data-theme="dark"] .stat-value { color: #f8fafc; }
        html[data-theme="dark"] .recent-item { border-color: #334155; }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (isset($dashboardRoleFlags))
                <div class="dash-card">
                    <h3 class="mgmt-title">Management Area</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <a href="{{ url('/') }}" target="_blank" class="btn-mgmt btn-indigo">View Front End</a>
                        @if ($dashboardRoleFlags['isStaffWriter'])
                            <a href="{{ route('staff.dashboard') }}" class="btn-mgmt btn-indigo">Staff Workspace</a>
                        @endif
                        <a href="{{ route('admin.customers.index') }}" class="btn-mgmt btn-slate">Customer Lookup</a>
                        <a href="{{ route('admin.finance.index') }}" class="btn-mgmt btn-slate">Finance</a>
                        <a href="{{ route('admin.campaigns.ads.index') }}" class="btn-mgmt btn-slate">Ad Campaigns</a>
                        <a href="{{ route('admin.campaigns.push.index') }}" class="btn-mgmt btn-slate">Push Campaigns</a>
                        <a href="{{ route('admin.wallet.index') }}" class="btn-mgmt btn-slate">Staff Wallets</a>
                        <a href="{{ route('admin.payout-requests.index') }}" class="btn-mgmt btn-slate">Payout Requests</a>
                        <a href="{{ route('dev.transport.setup') }}" class="btn-mgmt btn-slate">Transport Setup</a>
                        <a href="{{ route('transport.manager.dashboard') }}" class="btn-mgmt btn-slate">Transport Manager</a>
                        <a href="{{ route('admin.classifieds.index') }}" class="btn-mgmt btn-slate">Moderate Classifieds</a>
                        <a href="{{ route('admin.listings.create') }}" class="btn-mgmt btn-indigo">New Listing</a>
                        <a href="{{ route('admin.events.create') }}" class="btn-mgmt btn-indigo">New Event</a>
                        <a href="{{ route('admin.articles.create') }}" class="btn-mgmt btn-indigo">New Article</a>
                        <a href="{{ route('admin.writer-applications.index') }}" class="btn-mgmt btn-slate">Review Applications</a>
                    </div>
                </div>

                <div class="stat-grid">
                    <div class="stat-card"><p class="stat-label">Users</p><p class="stat-value">{{ $counts['users'] }}</p></div>
                    <div class="stat-card"><p class="stat-label">Listings</p><p class="stat-value">{{ $counts['listings'] }}</p></div>
                    <div class="stat-card"><p class="stat-label">Events</p><p class="stat-value">{{ $counts['events'] }}</p></div>
                    <div class="stat-card"><p class="stat-label">Articles</p><p class="stat-value">{{ $counts['articles'] }}</p></div>
                    <div class="stat-card"><p class="stat-label">Applications</p><p class="stat-value">{{ $counts['writerApplications'] }}</p></div>
                </div>

                <div class="dash-card">
                    <div class="recent-grid">
                        <div class="recent-section">
                            <h4>Recent Listings</h4>
                            @foreach ($latestListings as $item)
                                <div class="recent-item">
                                    <a href="{{ route('admin.listings.edit', $item) }}" class="recent-link">{{ $item->title }}</a>
                                    <p class="recent-meta">{{ ucfirst($item->status) }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="recent-section">
                            <h4>Recent Events</h4>
                            @foreach ($latestEvents as $item)
                                <div class="recent-item">
                                    <a href="{{ route('admin.events.edit', $item) }}" class="recent-link">{{ $item->title }}</a>
                                    <p class="recent-meta">{{ ucfirst($item->status) }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="recent-section">
                            <h4>Recent Articles</h4>
                            @foreach ($latestArticles as $item)
                                <div class="recent-item">
                                    <a href="{{ route('admin.articles.edit', $item) }}" class="recent-link">{{ $item->title }}</a>
                                    <p class="recent-meta">{{ ucfirst($item->status) }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="recent-section">
                            <h4>Recent Applications</h4>
                            @foreach ($latestWriterApplications as $item)
                                <div class="recent-item">
                                    <a href="{{ route('admin.writer-applications.show', $item) }}" class="recent-link">{{ $item->fullName() }}</a>
                                    <p class="recent-meta">{{ str_replace('_', ' ', ucfirst($item->status)) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="dash-card">
                    <p style="margin: 0;">You are signed in as <strong>{{ auth()->user()->name }}</strong>.</p>
                    <p style="margin: 0.5rem 0 0; color: #64748b;">Role: <strong>{{ ucfirst(auth()->user()->role) }}</strong></p>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
