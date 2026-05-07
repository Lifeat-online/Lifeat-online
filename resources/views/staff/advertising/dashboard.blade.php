<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Staff Advertising Dashboard</h2>
            <a href="{{ route('staff.dashboard') }}" class="underline text-sm text-gray-600 hover:text-gray-900">Back to staff workspace</a>
        </div>
    </x-slot>

    <style>
        .cardx { background: #ffffff; border: 1px solid #eef2f7; border-radius: 16px; padding: 1.5rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05); }
        .gridx { display: grid; gap: 1rem; }
        .rowx { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap: wrap; }
        .mutedx { color: #64748b; }
        .pill { display:inline-flex; align-items:center; gap:0.5rem; border:1px solid #e2e8f0; border-radius:999px; padding:0.3rem 0.6rem; font-weight:700; font-size:0.8rem; color:#334155; }
        .dot { width: 10px; height: 10px; border-radius: 999px; background: #94a3b8; }
        .dot.active { background:#10b981; }
        .dot.paused { background:#f59e0b; }
        .dot.draft { background:#64748b; }
        .dot.ready { background:#3b82f6; }
        .tablex { width:100%; border-collapse: collapse; }
        .tablex th, .tablex td { text-align:left; padding:0.65rem; border-bottom:1px solid #eef2f7; vertical-align: top; }
        .tablex th { font-size:0.75rem; text-transform: uppercase; letter-spacing:0.06em; color:#64748b; }
        .btnx { display:inline-flex; align-items:center; justify-content:center; padding:0.5rem 0.9rem; border-radius: 10px; font-weight:800; font-size:0.85rem; border:1px solid #e2e8f0; background:#f8fafc; color:#0f172a; cursor:pointer; }
        .btnx.primary { background: linear-gradient(135deg, #6366f1, #4f46e5); border-color: transparent; color: #ffffff; }
        .btnx:disabled { opacity: 0.6; cursor: not-allowed; }
        .inputx, .selectx, .textareax { width:100%; border:1px solid #e2e8f0; border-radius:10px; padding:0.55rem 0.75rem; }
        .textareax { min-height: 88px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 0.85rem; }
        .notice { border-radius: 14px; border: 1px solid #e2e8f0; padding: 0.75rem 1rem; background: #f1f5f9; color: #334155; }
        .error { background: rgba(220, 38, 38, 0.08); border-color: rgba(220, 38, 38, 0.2); color: #991b1b; }
        html[data-theme="dark"] .cardx { background: #111827; border-color: #1f2937; }
        html[data-theme="dark"] .mutedx { color: #94a3b8; }
        html[data-theme="dark"] .tablex th, html[data-theme="dark"] .tablex td { border-bottom-color: #1f2937; }
        html[data-theme="dark"] .btnx { background: #0b1220; border-color: #1f2937; color: #e5e7eb; }
        html[data-theme="dark"] .inputx, html[data-theme="dark"] .selectx, html[data-theme="dark"] .textareax { background:#0b1220; border-color:#1f2937; color:#e5e7eb; }
        html[data-theme="dark"] .notice { background:#0b1220; border-color:#1f2937; color:#e5e7eb; }
    </style>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6" id="staff-ad-dash">
            <div class="cardx">
                <div class="rowx">
                    <div>
                        <div class="mutedx" style="font-weight:800; text-transform:uppercase; letter-spacing:0.08em; font-size:0.75rem;">Assigned businesses</div>
                        <h3 style="font-weight:900; font-size:1.35rem; margin-top:0.35rem;">Manage advertising for your clients</h3>
                        <p class="mutedx" style="margin-top:0.35rem;">Select a business to view and edit campaign configuration. All changes are logged.</p>
                    </div>
                    <div style="min-width: 320px;">
                        <label for="business_id" class="mutedx" style="display:block; font-weight:800; font-size:0.8rem; margin-bottom:0.35rem;">Business</label>
                        <select id="business_id" class="selectx">
                            <option value="">Select…</option>
                            @foreach ($businesses as $business)
                                <option value="{{ $business->id }}">{{ $business->title }}</option>
                            @endforeach
                        </select>
                        <div style="margin-top:0.65rem;">
                            <a id="open-workspace" href="#" class="underline text-sm text-gray-600 hover:text-gray-900" style="pointer-events:none; opacity:0.6;">Open listing workspace</a>
                        </div>
                    </div>
                </div>
                <div id="staff-ad-status" class="notice" style="margin-top:1rem; display:none;"></div>
            </div>

            <div class="cardx" id="business-summary" style="display:none;">
                <div class="rowx">
                    <div>
                        <div class="mutedx" style="font-weight:800; text-transform:uppercase; letter-spacing:0.08em; font-size:0.75rem;">Business details</div>
                        <h3 id="biz-title" style="font-weight:900; font-size:1.35rem; margin-top:0.35rem;"></h3>
                        <div class="mutedx" id="biz-owner" style="margin-top:0.35rem;"></div>
                    </div>
                    <div class="pill" id="biz-entitlement" style="display:none;">
                        <span class="dot paused"></span>
                        <span>Subscription required</span>
                    </div>
                </div>
            </div>

            <div class="gridx" style="grid-template-columns: 1fr; display:none;" id="business-panels">
                <div class="cardx">
                    <div class="rowx">
                        <h3 style="font-weight:900; font-size:1.1rem;">Ad campaigns (banner / pop-up)</h3>
                        <span class="mutedx" id="ad-count"></span>
                    </div>
                    <div style="overflow:auto; margin-top:1rem;">
                        <table class="tablex" id="ad-table">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Status</th>
                                    <th>Placement</th>
                                    <th>Budget</th>
                                    <th>Schedule</th>
                                    <th>Targeting JSON</th>
                                    <th>Popup JSON</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div class="cardx">
                    <div class="rowx">
                        <h3 style="font-weight:900; font-size:1.1rem;">Push campaigns</h3>
                        <span class="mutedx" id="push-count"></span>
                    </div>
                    <div style="overflow:auto; margin-top:1rem;">
                        <table class="tablex" id="push-table">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Status</th>
                                    <th>Budget</th>
                                    <th>Schedule</th>
                                    <th>Audience</th>
                                    <th>Geo targeting</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <div class="cardx">
                    <div class="rowx">
                        <h3 style="font-weight:900; font-size:1.1rem;">Marketing integrations</h3>
                        <span class="mutedx">Email + Social</span>
                    </div>
                    <div class="gridx" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); margin-top:1rem;">
                        <div class="cardx" style="box-shadow:none;">
                            <h4 style="font-weight:900;">Email marketing</h4>
                            <div class="mutedx" style="margin-top:0.35rem;">Provider + settings for future integrations.</div>
                            <div style="margin-top:0.85rem;">
                                <label class="mutedx" style="font-weight:800; font-size:0.8rem;">Provider</label>
                                <input class="inputx" id="email-provider" placeholder="Mailchimp / Brevo / etc">
                            </div>
                            <div style="margin-top:0.65rem;">
                                <label class="mutedx" style="font-weight:800; font-size:0.8rem;">Status</label>
                                <select class="selectx" id="email-status">
                                    <option value="inactive">Inactive</option>
                                    <option value="active">Active</option>
                                </select>
                            </div>
                            <div style="margin-top:0.65rem;">
                                <label class="mutedx" style="font-weight:800; font-size:0.8rem;">Settings JSON</label>
                                <textarea class="textareax" id="email-settings">{}</textarea>
                            </div>
                            <div class="rowx" style="margin-top:0.75rem;">
                                <span class="mutedx" id="email-updated"></span>
                                <button class="btnx primary" type="button" id="email-save">Save</button>
                            </div>
                        </div>

                        <div class="cardx" style="box-shadow:none;">
                            <h4 style="font-weight:900;">Social ads</h4>
                            <div class="mutedx" style="margin-top:0.35rem;">Provider + settings for future integrations.</div>
                            <div style="margin-top:0.85rem;">
                                <label class="mutedx" style="font-weight:800; font-size:0.8rem;">Provider</label>
                                <input class="inputx" id="social-provider" placeholder="Meta / Google / etc">
                            </div>
                            <div style="margin-top:0.65rem;">
                                <label class="mutedx" style="font-weight:800; font-size:0.8rem;">Status</label>
                                <select class="selectx" id="social-status">
                                    <option value="inactive">Inactive</option>
                                    <option value="active">Active</option>
                                </select>
                            </div>
                            <div style="margin-top:0.65rem;">
                                <label class="mutedx" style="font-weight:800; font-size:0.8rem;">Settings JSON</label>
                                <textarea class="textareax" id="social-settings">{}</textarea>
                            </div>
                            <div class="rowx" style="margin-top:0.75rem;">
                                <span class="mutedx" id="social-updated"></span>
                                <button class="btnx primary" type="button" id="social-save">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const csrf = @json(csrf_token());
            const workspaceUrls = @json($businesses->mapWithKeys(fn ($b) => [(string) $b->id => route('account.listings.show', $b)])->all());
            const select = document.getElementById('business_id');
            const openWorkspace = document.getElementById('open-workspace');
            const statusBox = document.getElementById('staff-ad-status');
            const summaryBox = document.getElementById('business-summary');
            const panels = document.getElementById('business-panels');

            const bizTitle = document.getElementById('biz-title');
            const bizOwner = document.getElementById('biz-owner');
            const bizEntitlement = document.getElementById('biz-entitlement');

            const adTbody = document.querySelector('#ad-table tbody');
            const pushTbody = document.querySelector('#push-table tbody');
            const adCount = document.getElementById('ad-count');
            const pushCount = document.getElementById('push-count');

            const emailProvider = document.getElementById('email-provider');
            const emailStatus = document.getElementById('email-status');
            const emailSettings = document.getElementById('email-settings');
            const emailUpdated = document.getElementById('email-updated');
            const emailSave = document.getElementById('email-save');

            const socialProvider = document.getElementById('social-provider');
            const socialStatus = document.getElementById('social-status');
            const socialSettings = document.getElementById('social-settings');
            const socialUpdated = document.getElementById('social-updated');
            const socialSave = document.getElementById('social-save');

            const endpoints = {
                summary: (listingId) => @json(route('api.staff.advertising.summary', ['listing' => '___'])) .replace('___', String(listingId)),
                updateAd: (id) => @json(route('api.staff.advertising.ad-campaigns.update', ['adCampaign' => '___'])) .replace('___', String(id)),
                updatePush: (id) => @json(route('api.staff.advertising.push-campaigns.update', ['pushCampaign' => '___'])) .replace('___', String(id)),
                updateIntegration: (listingId, type) => @json(route('api.staff.advertising.integrations.update', ['listing' => '___', 'type' => '___TYPE___'])) .replace('___', String(listingId)).replace('___TYPE___', String(type)),
            };

            const showStatus = (message, isError = false) => {
                if (!statusBox) return;
                statusBox.textContent = message;
                statusBox.style.display = 'block';
                statusBox.classList.toggle('error', !!isError);
            };

            const clearStatus = () => {
                if (!statusBox) return;
                statusBox.style.display = 'none';
                statusBox.textContent = '';
                statusBox.classList.remove('error');
            };

            const pill = (status) => {
                const s = String(status || 'draft');
                return `<span class="pill"><span class="dot ${s}"></span><span>${s.replaceAll('_', ' ')}</span></span>`;
            };

            const parseJson = (value) => {
                const v = String(value || '').trim();
                if (v === '') return null;
                return JSON.parse(v);
            };

            const isoInput = (value) => {
                if (!value) return '';
                const d = new Date(value);
                const pad = (n) => String(n).padStart(2, '0');
                return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            };

            const readIsoFromLocalInput = (value) => {
                const v = String(value || '').trim();
                if (v === '') return null;
                const date = new Date(v);
                return isNaN(date.getTime()) ? null : date.toISOString();
            };

            let currentListingId = null;
            let currentIntegrations = {};

            async function loadSummary(listingId) {
                currentListingId = listingId;
                clearStatus();

                if (!listingId) {
                    summaryBox.style.display = 'none';
                    panels.style.display = 'none';
                    return;
                }

                showStatus('Loading…');

                const res = await fetch(endpoints.summary(listingId), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const data = await res.json().catch(() => null);
                if (!data || !data.listing) {
                    showStatus('Failed to load business summary.', true);
                    return;
                }

                summaryBox.style.display = 'block';
                panels.style.display = 'grid';

                bizTitle.textContent = data.listing.title || '';
                bizOwner.textContent = data.listing.owner ? `${data.listing.owner.name} · ${data.listing.owner.email}` : 'No owner assigned';
                bizEntitlement.style.display = data.listing.has_active_business_entitlement ? 'none' : 'inline-flex';

                const adCampaigns = data.ad_campaigns || [];
                const pushCampaigns = data.push_campaigns || [];
                adCount.textContent = `${adCampaigns.length} total`;
                pushCount.textContent = `${pushCampaigns.length} total`;

                adTbody.innerHTML = '';
                adCampaigns.forEach((c) => adTbody.appendChild(renderAdRow(c)));

                pushTbody.innerHTML = '';
                pushCampaigns.forEach((c) => pushTbody.appendChild(renderPushRow(c)));

                currentIntegrations = {};
                (data.integrations || []).forEach((i) => currentIntegrations[i.type] = i);

                hydrateIntegration('email_marketing', emailProvider, emailStatus, emailSettings, emailUpdated);
                hydrateIntegration('social_ads', socialProvider, socialStatus, socialSettings, socialUpdated);

                clearStatus();
            }

            function hydrateIntegration(type, providerEl, statusEl, settingsEl, updatedEl) {
                const existing = currentIntegrations[type];
                providerEl.value = existing?.provider || '';
                statusEl.value = existing?.status || 'inactive';
                settingsEl.value = JSON.stringify(existing?.settings || {}, null, 2);
                updatedEl.textContent = existing?.updated_at ? `Updated: ${new Date(existing.updated_at).toLocaleString()}` : '';
                providerEl.dataset.expectedUpdatedAt = existing?.updated_at || '';
            }

            function renderAdRow(c) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div style="font-weight:900;">${escapeHtml(c.title || 'Campaign')}</div>
                        <div class="mutedx">CTR: ${Number(c.ctr || 0).toFixed(2)}% · ${Number(c.impressions || 0)} impressions · ${Number(c.clicks || 0)} clicks</div>
                    </td>
                    <td>
                        <select class="selectx" data-field="status">
                            ${['draft','ready','active','paused'].map(s => `<option value="${s}" ${c.status===s?'selected':''}>${s}</option>`).join('')}
                        </select>
                    </td>
                    <td>
                        <select class="selectx" data-field="placement">
                            ${['banner','popup'].map(s => `<option value="${s}" ${c.placement===s?'selected':''}>${s}</option>`).join('')}
                        </select>
                    </td>
                    <td>
                        <div style="display:flex; gap:0.5rem;">
                            <input class="inputx" data-field="budget_amount" placeholder="0.00" value="${c.budget_amount ?? ''}">
                            <input class="inputx" data-field="budget_currency" style="max-width:88px;" value="${c.budget_currency || 'ZAR'}">
                        </div>
                    </td>
                    <td>
                        <div class="gridx" style="gap:0.5rem;">
                            <input class="inputx" type="datetime-local" data-field="start_at" value="${isoInput(c.start_at)}">
                            <input class="inputx" type="datetime-local" data-field="end_at" value="${isoInput(c.end_at)}">
                        </div>
                    </td>
                    <td><textarea class="textareax" data-field="targeting">${JSON.stringify(c.targeting || {}, null, 2)}</textarea></td>
                    <td><textarea class="textareax" data-field="popup_settings">${JSON.stringify(c.popup_settings || {}, null, 2)}</textarea></td>
                    <td style="min-width:140px;">
                        <div class="gridx" style="gap:0.5rem;">
                            <button type="button" class="btnx primary" data-action="save">Save</button>
                            <div class="mutedx" style="font-size:0.8rem;" data-action="meta">${c.updated_at ? 'Updated: ' + new Date(c.updated_at).toLocaleString() : ''}</div>
                        </div>
                    </td>
                `;
                tr.querySelector('[data-action="save"]').addEventListener('click', async () => {
                    await saveAdCampaign(c.id, c.updated_at, tr);
                });
                return tr;
            }

            function renderPushRow(c) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <div style="font-weight:900;">${escapeHtml(c.title || 'Campaign')}</div>
                        <div class="mutedx">Open rate: ${Number(c.open_rate || 0).toFixed(2)}% · Opens: ${Number(c.open_count || 0)}</div>
                    </td>
                    <td>
                        <select class="selectx" data-field="status">
                            ${['draft','ready','scheduled','active'].map(s => `<option value="${s}" ${c.status===s?'selected':''}>${s}</option>`).join('')}
                        </select>
                    </td>
                    <td>
                        <div style="display:flex; gap:0.5rem;">
                            <input class="inputx" data-field="budget_amount" placeholder="0.00" value="${c.budget_amount ?? ''}">
                            <input class="inputx" data-field="budget_currency" style="max-width:88px;" value="${c.budget_currency || 'ZAR'}">
                        </div>
                    </td>
                    <td>
                        <input class="inputx" type="datetime-local" data-field="schedule_at" value="${isoInput(c.schedule_at)}">
                    </td>
                    <td>
                        <input class="inputx" data-field="audience_scope" value="${escapeAttr(c.audience_scope || '')}">
                    </td>
                    <td>
                        <div class="gridx" style="gap:0.5rem;">
                            <input class="inputx" data-field="target_city" placeholder="City" value="${escapeAttr(c.target_city || '')}">
                            <input class="inputx" data-field="target_region" placeholder="Region" value="${escapeAttr(c.target_region || '')}">
                            <input class="inputx" data-field="radius_km" placeholder="Radius km" value="${c.radius_km ?? ''}">
                        </div>
                    </td>
                    <td style="min-width:140px;">
                        <div class="gridx" style="gap:0.5rem;">
                            <button type="button" class="btnx primary" data-action="save">Save</button>
                            <div class="mutedx" style="font-size:0.8rem;" data-action="meta">${c.updated_at ? 'Updated: ' + new Date(c.updated_at).toLocaleString() : ''}</div>
                        </div>
                    </td>
                `;
                tr.querySelector('[data-action="save"]').addEventListener('click', async () => {
                    await savePushCampaign(c.id, c.updated_at, tr);
                });
                return tr;
            }

            async function saveAdCampaign(id, expectedUpdatedAt, row) {
                const btn = row.querySelector('[data-action="save"]');
                btn.disabled = true;
                clearStatus();

                try {
                    const payload = {
                        expected_updated_at: expectedUpdatedAt,
                        status: row.querySelector('[data-field="status"]').value,
                        placement: row.querySelector('[data-field="placement"]').value,
                        budget_amount: row.querySelector('[data-field="budget_amount"]').value || null,
                        budget_currency: row.querySelector('[data-field="budget_currency"]').value || 'ZAR',
                        start_at: readIsoFromLocalInput(row.querySelector('[data-field="start_at"]').value),
                        end_at: readIsoFromLocalInput(row.querySelector('[data-field="end_at"]').value),
                        targeting: parseJson(row.querySelector('[data-field="targeting"]').value) || {},
                        popup_settings: parseJson(row.querySelector('[data-field="popup_settings"]').value) || {},
                    };

                    const res = await fetch(endpoints.updateAd(id), {
                        method: 'PUT',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json().catch(() => null);
                    if (!res.ok) {
                        showStatus(data?.message || 'Failed to save ad campaign.', true);
                        return;
                    }

                    showStatus('Saved.', false);
                    await loadSummary(currentListingId);
                } catch (e) {
                    showStatus('Failed to save ad campaign.', true);
                } finally {
                    btn.disabled = false;
                }
            }

            async function savePushCampaign(id, expectedUpdatedAt, row) {
                const btn = row.querySelector('[data-action="save"]');
                btn.disabled = true;
                clearStatus();

                try {
                    const payload = {
                        expected_updated_at: expectedUpdatedAt,
                        status: row.querySelector('[data-field="status"]').value,
                        budget_amount: row.querySelector('[data-field="budget_amount"]').value || null,
                        budget_currency: row.querySelector('[data-field="budget_currency"]').value || 'ZAR',
                        schedule_at: readIsoFromLocalInput(row.querySelector('[data-field="schedule_at"]').value),
                        audience_scope: row.querySelector('[data-field="audience_scope"]').value || null,
                        target_city: row.querySelector('[data-field="target_city"]').value || null,
                        target_region: row.querySelector('[data-field="target_region"]').value || null,
                        radius_km: row.querySelector('[data-field="radius_km"]').value || null,
                    };

                    const res = await fetch(endpoints.updatePush(id), {
                        method: 'PUT',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json().catch(() => null);
                    if (!res.ok) {
                        showStatus(data?.message || 'Failed to save push campaign.', true);
                        return;
                    }

                    showStatus('Saved.', false);
                    await loadSummary(currentListingId);
                } catch (e) {
                    showStatus('Failed to save push campaign.', true);
                } finally {
                    btn.disabled = false;
                }
            }

            async function saveIntegration(type, providerEl, statusEl, settingsEl, updatedEl, saveBtn) {
                saveBtn.disabled = true;
                clearStatus();

                try {
                    const expected = providerEl.dataset.expectedUpdatedAt || null;
                    const payload = {
                        expected_updated_at: expected || null,
                        provider: providerEl.value || null,
                        status: statusEl.value,
                        settings: parseJson(settingsEl.value) || {},
                    };

                    const res = await fetch(endpoints.updateIntegration(currentListingId, type), {
                        method: 'PUT',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json().catch(() => null);
                    if (!res.ok) {
                        showStatus(data?.message || 'Failed to save integration.', true);
                        return;
                    }

                    showStatus('Saved.', false);
                    await loadSummary(currentListingId);
                } catch (e) {
                    showStatus('Failed to save integration.', true);
                } finally {
                    saveBtn.disabled = false;
                }
            }

            function escapeHtml(text) {
                const t = String(text || '');
                return t.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
            }

            function escapeAttr(text) {
                return escapeHtml(text);
            }

            select.addEventListener('change', async () => {
                const url = workspaceUrls[String(select.value || '')] || null;
                if (openWorkspace) {
                    openWorkspace.href = url || '#';
                    openWorkspace.style.pointerEvents = url ? 'auto' : 'none';
                    openWorkspace.style.opacity = url ? '1' : '0.6';
                }
                await loadSummary(select.value);
            });

            emailSave.addEventListener('click', async () => {
                await saveIntegration('email_marketing', emailProvider, emailStatus, emailSettings, emailUpdated, emailSave);
            });

            socialSave.addEventListener('click', async () => {
                await saveIntegration('social_ads', socialProvider, socialStatus, socialSettings, socialUpdated, socialSave);
            });
        })();
    </script>
</x-app-layout>
