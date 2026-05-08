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

            @if (isset($dashboardRoleFlags) && ($dashboardRoleFlags['canUseDevTools'] ?? false))
                <div class="dash-card" id="dev-tools">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-wrap:wrap;">
                        <h3 class="mgmt-title" style="margin-bottom:0;">Dev</h3>
                        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                            <button class="btn-mgmt btn-slate" type="button" data-dev-action="check">Check for updates</button>
                            <button class="btn-mgmt btn-indigo" type="button" data-dev-action="apply" disabled>Roll out update</button>
                        </div>
                    </div>

                    <div id="dev-update-banner" style="margin-top:1rem; display:none; padding:0.75rem 1rem; border-radius:14px; border:1px solid rgba(15, 23, 42, 0.12); background:rgba(15, 23, 42, 0.04); color:#0f172a;"></div>

                    <div style="margin-top:1rem; display:grid; gap:0.75rem;">
                        <div class="stat-card" style="box-shadow:none;">
                            <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                                <div style="min-width:240px;">
                                    <div style="color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">Git</div>
                                    <div style="font-weight:900; font-size:1.05rem; margin-top:0.15rem;" id="dev-origin-url">Origin: -</div>
                                    <div style="color:#64748b; font-size:0.85rem; margin-top:0.25rem;" id="dev-auth-mode">Auth: -</div>
                                </div>
                                <div style="flex:1; min-width:320px; max-width:640px;">
                                    <div style="display:grid; gap:0.5rem;">
                                        <input id="dev-origin-input" class="w-full rounded-md border-gray-300" placeholder="Origin URL (SSH recommended)">
                                        <input id="dev-token-input" type="password" class="w-full rounded-md border-gray-300" placeholder="GitHub token (optional)">
                                        <label style="display:flex; align-items:center; gap:0.5rem; color:#64748b; font-size:0.85rem;">
                                            <input id="dev-clear-token" type="checkbox">
                                            Clear saved token
                                        </label>
                                        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                            <button class="btn-mgmt btn-slate" type="button" id="dev-save-creds">Save credentials</button>
                                            <button class="btn-mgmt btn-indigo" type="button" id="dev-test-creds">Test access</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card" style="box-shadow:none;">
                            <div style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                                <div>
                                    <div style="color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">Current</div>
                                    <div style="font-weight:900; font-size:1.05rem; margin-top:0.15rem;" id="dev-current-version">-</div>
                                    <div style="color:#64748b; font-size:0.85rem; margin-top:0.25rem;" id="dev-current-hash">-</div>
                                </div>
                                <div>
                                    <div style="color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">Remote</div>
                                    <div style="font-weight:900; font-size:1.05rem; margin-top:0.15rem;" id="dev-branch">-</div>
                                    <div style="color:#64748b; font-size:0.85rem; margin-top:0.25rem;" id="dev-remote-hash">-</div>
                                </div>
                                <div>
                                    <div style="color:#64748b; font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;">Status</div>
                                    <div style="font-weight:900; font-size:1.05rem; margin-top:0.15rem;" id="dev-update-availability">-</div>
                                    <div style="color:#64748b; font-size:0.85rem; margin-top:0.25rem;" id="dev-dirty">-</div>
                                </div>
                            </div>
                        </div>

                        <pre id="dev-update-log" style="display:none; white-space:pre-wrap; word-break:break-word; padding:0.9rem 1rem; border-radius:14px; border:1px solid rgba(15, 23, 42, 0.12); background:#0b1220; color:#e5e7eb; font-size:0.85rem; line-height:1.4; max-height:320px; overflow:auto;"></pre>
                    </div>
                </div>

                <script>
                    (() => {
                        const root = document.getElementById('dev-tools');
                        if (!root) return;

                        const csrf = @json(csrf_token());
                        const endpoints = {
                            status: @json(route('dev.updates.status')),
                            saveCreds: @json(route('dev.updates.credentials.save')),
                            testCreds: @json(route('dev.updates.credentials.test')),
                            apply: @json(route('dev.updates.apply')),
                        };

                        const banner = document.getElementById('dev-update-banner');
                        const logBox = document.getElementById('dev-update-log');

                        const currentVersionEl = document.getElementById('dev-current-version');
                        const currentHashEl = document.getElementById('dev-current-hash');
                        const branchEl = document.getElementById('dev-branch');
                        const remoteHashEl = document.getElementById('dev-remote-hash');
                        const availabilityEl = document.getElementById('dev-update-availability');
                        const dirtyEl = document.getElementById('dev-dirty');

                        const originUrlEl = document.getElementById('dev-origin-url');
                        const authModeEl = document.getElementById('dev-auth-mode');
                        const originInput = document.getElementById('dev-origin-input');
                        const tokenInput = document.getElementById('dev-token-input');
                        const clearToken = document.getElementById('dev-clear-token');
                        const saveCredsBtn = document.getElementById('dev-save-creds');
                        const testCredsBtn = document.getElementById('dev-test-creds');

                        const checkBtn = root.querySelector('[data-dev-action="check"]');
                        const applyBtn = root.querySelector('[data-dev-action="apply"]');

                        const setBanner = (text, isError = false) => {
                            if (!banner) return;
                            banner.textContent = text || '';
                            banner.style.display = text ? 'block' : 'none';
                            banner.style.borderColor = isError ? 'rgba(220, 38, 38, 0.25)' : 'rgba(15, 23, 42, 0.12)';
                            banner.style.background = isError ? 'rgba(220, 38, 38, 0.08)' : 'rgba(15, 23, 42, 0.04)';
                            banner.style.color = isError ? '#991b1b' : '#0f172a';
                        };

                        const setLog = (text) => {
                            if (!logBox) return;
                            logBox.textContent = text || '';
                            logBox.style.display = text ? 'block' : 'none';
                        };

                        const normalizeHash = (hash) => {
                            const h = String(hash || '').trim();
                            return h ? h.slice(0, 12) : '-';
                        };

                        const renderStatus = (data) => {
                            const enabled = !!data.enabled;
                            const updateAvailable = !!data.update_available;

                            if (originUrlEl) {
                                originUrlEl.textContent = `Origin: ${data.origin_url || '-'}`;
                            }
                            if (authModeEl) {
                                const mode = data.auth_mode || 'none';
                                const token = data.has_token ? 'token saved' : 'no token';
                                authModeEl.textContent = `Auth: ${mode}${mode === 'token' ? ` (${token})` : ''}`;
                            }
                            if (originInput && !originInput.value) {
                                originInput.value = data.origin_url || '';
                            }

                            currentVersionEl.textContent = data.current_version || '-';
                            currentHashEl.textContent = data.local_hash ? `Commit: ${normalizeHash(data.local_hash)}` : 'Commit: -';
                            branchEl.textContent = data.branch ? `Branch: ${data.branch}` : 'Branch: -';
                            remoteHashEl.textContent = data.remote_hash ? `Remote: ${normalizeHash(data.remote_hash)}` : 'Remote: -';

                            if (!enabled) {
                                availabilityEl.textContent = 'Disabled';
                            } else if (!data.git_available) {
                                availabilityEl.textContent = 'Git not available';
                            } else if (!data.is_repository) {
                                availabilityEl.textContent = 'Not a git repo';
                            } else if (updateAvailable) {
                                availabilityEl.textContent = 'Update available';
                            } else {
                                availabilityEl.textContent = 'Up to date';
                            }

                            dirtyEl.textContent = data.is_dirty === true ? 'Working tree: dirty' : data.is_dirty === false ? 'Working tree: clean' : 'Working tree: -';

                            if (applyBtn) {
                                applyBtn.disabled = !enabled || !data.git_available || !data.is_repository || !updateAvailable;
                            }
                        };

                        const saveCredentials = async () => {
                            const originUrl = originInput ? originInput.value.trim() : '';
                            const token = tokenInput ? tokenInput.value.trim() : '';
                            const clear = !!(clearToken && clearToken.checked);

                            setBanner('Saving git credentials…');
                            if (saveCredsBtn) saveCredsBtn.disabled = true;
                            if (testCredsBtn) testCredsBtn.disabled = true;

                            try {
                                const payload = {
                                    origin_url: originUrl || null,
                                    clear_token: clear,
                                };
                                if (token) payload.token = token;

                                const res = await fetch(endpoints.saveCreds, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify(payload),
                                    credentials: 'same-origin',
                                });

                                const data = await res.json().catch(() => ({}));
                                if (!res.ok || !data.ok) {
                                    setBanner(data.message || 'Failed to save credentials.', true);
                                    return;
                                }

                                if (tokenInput) tokenInput.value = '';
                                if (clearToken) clearToken.checked = false;

                                setBanner('Credentials saved.');
                                await fetchStatus();
                            } catch (_) {
                                setBanner('Network error while saving credentials.', true);
                            } finally {
                                if (saveCredsBtn) saveCredsBtn.disabled = false;
                                if (testCredsBtn) testCredsBtn.disabled = false;
                            }
                        };

                        const testCredentials = async () => {
                            setBanner('Testing remote access…');
                            setLog('');
                            if (saveCredsBtn) saveCredsBtn.disabled = true;
                            if (testCredsBtn) testCredsBtn.disabled = true;

                            try {
                                const res = await fetch(endpoints.testCreds, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({}),
                                    credentials: 'same-origin',
                                });

                                const data = await res.json().catch(() => ({}));
                                if (!res.ok || !data.ok) {
                                    const msg = data.error_output || data.message || 'Remote access test failed.';
                                    setBanner(msg, true);
                                    if (data.output || data.error_output) {
                                        setLog([data.output, data.error_output].filter(Boolean).join('\n'));
                                    }
                                    return;
                                }

                                const out = [data.output, data.error_output].filter(Boolean).join('\n');
                                if (out) setLog(out);
                                setBanner('Remote access OK.');
                            } catch (_) {
                                setBanner('Network error while testing remote access.', true);
                            } finally {
                                if (saveCredsBtn) saveCredsBtn.disabled = false;
                                if (testCredsBtn) testCredsBtn.disabled = false;
                            }
                        };

                        const fetchStatus = async () => {
                            setBanner('Checking update status…');
                            setLog('');
                            if (checkBtn) checkBtn.disabled = true;
                            if (applyBtn) applyBtn.disabled = true;

                            try {
                                const res = await fetch(endpoints.status, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                                const data = await res.json().catch(() => ({}));
                                if (!res.ok || !data.ok) {
                                    setBanner(data.message || 'Failed to check status.', true);
                                    return;
                                }
                                renderStatus(data);
                                setBanner('Status updated.');
                            } catch (_) {
                                setBanner('Network error while checking status.', true);
                            } finally {
                                if (checkBtn) checkBtn.disabled = false;
                            }
                        };

                        const applyUpdate = async () => {
                            const ok = window.confirm('Roll out the latest update now? This will run git pull + composer + migrations.');
                            if (!ok) return;

                            setBanner('Applying update…');
                            setLog('');
                            if (checkBtn) checkBtn.disabled = true;
                            if (applyBtn) applyBtn.disabled = true;

                            try {
                                const res = await fetch(endpoints.apply, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': csrf,
                                        'Accept': 'application/json',
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({}),
                                    credentials: 'same-origin',
                                });

                                const data = await res.json().catch(() => ({}));
                                if (!res.ok || !data.ok) {
                                    setBanner(data.message || 'Update failed.', true);
                                    return;
                                }

                                const lines = (data.log || [])
                                    .flatMap((step) => {
                                        const cmd = Array.isArray(step.command) ? step.command.join(' ') : '';
                                        const out = String(step.output || '').trim();
                                        const err = String(step.error_output || '').trim();
                                        const parts = [];
                                        parts.push(`$ ${cmd}`);
                                        if (out) parts.push(out);
                                        if (err) parts.push(err);
                                        return parts;
                                    })
                                    .join('\n\n');

                                setLog(lines);
                                if (data.status) renderStatus(data.status);
                                setBanner(data.message || 'Update complete.');
                            } catch (_) {
                                setBanner('Network error while applying update.', true);
                            } finally {
                                if (checkBtn) checkBtn.disabled = false;
                            }
                        };

                        checkBtn?.addEventListener('click', fetchStatus);
                        applyBtn?.addEventListener('click', applyUpdate);
                        saveCredsBtn?.addEventListener('click', saveCredentials);
                        testCredsBtn?.addEventListener('click', testCredentials);

                        fetchStatus();
                    })();
                </script>
            @endif
        </div>
    </div>
</x-app-layout>
