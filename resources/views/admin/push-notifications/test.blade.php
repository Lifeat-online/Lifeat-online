<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Push Notifications</h2>
                <p class="mt-1 text-sm text-gray-500">Compose, test, and dispatch rich browser push notifications.</p>
            </div>
            <a href="{{ route('admin.campaigns.push.index') }}" class="button-link !min-h-0 px-4 py-2 text-sm">Push Campaigns</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->has('push'))
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    {{ $errors->first('push') }}
                </div>
            @endif

            <div class="app-stat-grid">
                <div class="app-stat">
                    <p class="text-sm text-gray-500">VAPID Keys</p>
                    <p class="mt-2 text-2xl font-semibold {{ $isConfigured ? 'text-emerald-700' : 'text-red-700' }}">{{ $isConfigured ? 'Ready' : 'Missing' }}</p>
                </div>
                <div class="app-stat">
                    <p class="text-sm text-gray-500">Active Subscriptions</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $activeSubscriptionCount }}</p>
                </div>
                <div class="app-stat">
                    <p class="text-sm text-gray-500">This Browser</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $ownSubscriptionCount }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="button-link !min-h-0 px-3 py-2 text-sm" data-push-toggle hidden>Enable alerts</button>
                        <button type="button" class="button-link btn-soft !min-h-0 px-3 py-2 text-sm" data-push-tone-preview hidden>Preview tone</button>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.push-notifications.store') }}" class="space-y-6">
                @csrf

                <div class="app-card">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Message</h3>
                            <p class="mt-1 text-sm text-gray-500">Send to your browser first, then switch to all active subscribers when the payload is right.</p>
                        </div>
                        <div class="w-full sm:w-64">
                            <label for="audience" class="block text-sm font-medium text-gray-700">Audience</label>
                            <select id="audience" name="audience" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="self" @selected(old('audience', 'self') === 'self')>Only my browser subscriptions</option>
                                <option value="all" @selected(old('audience') === 'all')>All active subscribers</option>
                            </select>
                            <x-input-error :messages="$errors->get('audience')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 app-form-grid">
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" maxlength="80" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <div>
                            <label for="url" class="block text-sm font-medium text-gray-700">Click URL</label>
                            <input id="url" name="url" type="url" value="{{ old('url', route('admin.dashboard')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <x-input-error :messages="$errors->get('url')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <label for="body" class="block text-sm font-medium text-gray-700">Message</label>
                            <textarea id="body" name="body" rows="3" maxlength="180" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body') }}</textarea>
                            <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="app-card">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Marketing attachment</h3>
                            <p class="mt-1 text-sm text-gray-500">Attach a Lifeat item and the click URL, image, and action buttons can be filled from it.</p>
                        </div>
                        <button type="button" class="button-link btn-soft !min-h-0 px-3 py-2 text-sm" data-attachment-apply>Use attachment in message</button>
                    </div>

                    <div class="mt-5 grid gap-5 lg:grid-cols-[0.9fr_1.3fr]">
                        <div>
                            <label for="attachment_type" class="block text-sm font-medium text-gray-700">Attach</label>
                            <select id="attachment_type" name="attachment_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" data-attachment-type>
                                <option value="">No attachment / custom URL</option>
                                @foreach ($attachmentGroups as $type => $group)
                                    <option value="{{ $type }}" @selected(old('attachment_type') === $type)>{{ $group['label'] }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('attachment_type')" class="mt-2" />
                        </div>

                        <div>
                            <label for="attachment_id" class="block text-sm font-medium text-gray-700">Item</label>
                            <select id="attachment_id" name="attachment_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" data-attachment-id>
                                <option value="">Choose an item</option>
                                @foreach ($attachmentGroups as $type => $group)
                                    @foreach ($group['items'] as $item)
                                        <option value="{{ $item['id'] }}" data-type="{{ $type }}" data-key="{{ $type }}:{{ $item['id'] }}" @selected(old('attachment_type') === $type && (string) old('attachment_id') === (string) $item['id'])>
                                            {{ $item['label'] }}{{ $item['meta'] ? ' - '.$item['meta'] : '' }}
                                        </option>
                                    @endforeach
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('attachment_id')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-5 rounded-md border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-950" data-attachment-preview hidden>
                        <div class="font-semibold" data-attachment-preview-title></div>
                        <div class="mt-1 text-indigo-800" data-attachment-preview-meta></div>
                        <div class="mt-3 break-all text-xs text-indigo-700" data-attachment-preview-url></div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="app-card">
                        <h3 class="text-lg font-semibold text-gray-900">Visuals</h3>
                        <div class="mt-5 grid gap-5">
                            <div>
                                <label for="icon" class="block text-sm font-medium text-gray-700">Icon URL</label>
                                <input id="icon" name="icon" type="url" value="{{ old('icon', asset('pwa/icon-192.png')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('icon')" class="mt-2" />
                            </div>

                            <div>
                                <label for="badge" class="block text-sm font-medium text-gray-700">Badge URL</label>
                                <input id="badge" name="badge" type="url" value="{{ old('badge', asset('pwa/favicon-32x32.png')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('badge')" class="mt-2" />
                            </div>

                            <div>
                                <label for="image" class="block text-sm font-medium text-gray-700">Hero Image URL</label>
                                <input id="image" name="image" type="url" value="{{ old('image') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('image')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="app-card">
                        <h3 class="text-lg font-semibold text-gray-900">Delivery</h3>
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="urgency" class="block text-sm font-medium text-gray-700">Urgency</label>
                                <select id="urgency" name="urgency" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach (['normal' => 'Normal', 'high' => 'High', 'low' => 'Low', 'very-low' => 'Very low'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('urgency', 'normal') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('urgency')" class="mt-2" />
                            </div>

                            <div>
                                <label for="ttl" class="block text-sm font-medium text-gray-700">TTL seconds</label>
                                <input id="ttl" name="ttl" type="number" min="0" max="2419200" value="{{ old('ttl', 86400) }}" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('ttl')" class="mt-2" />
                            </div>

                            <div>
                                <label for="tag" class="block text-sm font-medium text-gray-700">Notification tag</label>
                                <input id="tag" name="tag" type="text" value="{{ old('tag', 'admin-broadcast') }}" maxlength="80" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('tag')" class="mt-2" />
                            </div>

                            <div>
                                <label for="topic" class="block text-sm font-medium text-gray-700">Web Push topic</label>
                                <input id="topic" name="topic" type="text" value="{{ old('topic', 'admin-broadcast') }}" maxlength="32" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <x-input-error :messages="$errors->get('topic')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="app-card">
                        <h3 class="text-lg font-semibold text-gray-900">Behavior</h3>
                        <div class="mt-5 grid gap-4">
                            <label class="flex items-start gap-3">
                                <input name="require_interaction" value="1" type="checkbox" @checked(old('require_interaction')) class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span><span class="block text-sm font-medium text-gray-800">Require interaction</span><span class="block text-sm text-gray-500">Keep supported desktop notifications visible until clicked or dismissed.</span></span>
                            </label>
                            <label class="flex items-start gap-3">
                                <input name="renotify" value="1" type="checkbox" @checked(old('renotify', true)) class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span><span class="block text-sm font-medium text-gray-800">Re-notify matching tag</span><span class="block text-sm text-gray-500">Alert again when replacing an existing notification with the same tag.</span></span>
                            </label>
                            <label class="flex items-start gap-3">
                                <input name="silent" value="1" type="checkbox" @checked(old('silent')) class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span><span class="block text-sm font-medium text-gray-800">Silent notification</span><span class="block text-sm text-gray-500">Suppress browser/OS sound and vibration where supported.</span></span>
                            </label>
                            <label class="flex items-start gap-3">
                                <input name="play_tone" value="1" type="checkbox" @checked(old('play_tone', true)) class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                <span><span class="block text-sm font-medium text-gray-800">Play app tone in open tabs</span><span class="block text-sm text-gray-500">Uses Web Audio when Lifeat is open; closed-background sound is controlled by the device.</span></span>
                            </label>
                        </div>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="tone" class="block text-sm font-medium text-gray-700">Tone</label>
                                <select id="tone" name="tone" required data-push-tone-select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach (['chime' => 'Chime', 'bell' => 'Bell', 'urgent' => 'Urgent', 'soft' => 'Soft'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('tone', 'chime') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('tone')" class="mt-2" />
                            </div>

                            <div>
                                <label for="vibration" class="block text-sm font-medium text-gray-700">Vibration</label>
                                <select id="vibration" name="vibration" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach (['none' => 'None', 'short' => 'Short', 'double' => 'Double', 'urgent' => 'Urgent'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('vibration', 'double') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('vibration')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="app-card">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Actions</h3>
                                <p class="mt-1 text-sm text-gray-500">Pick a preset, attach an item, or fine-tune the two buttons shown by supported browsers.</p>
                            </div>
                            <select class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-48" data-action-preset>
                                <option value="attachment">Use attachment</option>
                                <option value="marketing">Marketing pair</option>
                                <option value="browse">Browse pair</option>
                                <option value="custom">Custom</option>
                                <option value="clear">No buttons</option>
                            </select>
                        </div>

                        <div class="mt-5 grid gap-5">
                            @foreach ([1, 2] as $index)
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <label for="action_{{ $index }}_title" class="block text-sm font-medium text-gray-700">{{ $index === 1 ? 'Primary button' : 'Secondary button' }}</label>
                                        <input id="action_{{ $index }}_title" name="action_{{ $index }}_title" type="text" value="{{ old("action_{$index}_title") }}" maxlength="24" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <x-input-error :messages="$errors->get('action_'.$index.'_title')" class="mt-2" />
                                    </div>
                                    <div>
                                        <label for="action_{{ $index }}_url" class="block text-sm font-medium text-gray-700">Button URL</label>
                                        <input id="action_{{ $index }}_url" name="action_{{ $index }}_url" type="url" value="{{ old("action_{$index}_url") }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <x-input-error :messages="$errors->get('action_'.$index.'_url')" class="mt-2" />
                                    </div>
                                </div>
                            @endforeach
                            <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-500">
                                Click URL controls where the notification itself opens. Buttons can point somewhere different, such as a voucher claim page plus the business profile.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="button w-full sm:w-auto" onclick="return document.getElementById('audience')?.value !== 'all' || confirm('Send this push notification to all active subscribers now?');">Send Push</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (() => {
            const presets = @json($attachmentPresets);
            const routes = {
                directory: @json(route('directory.index')),
                events: @json(route('events.index')),
                vouchers: @json(route('vouchers.index')),
                articles: @json(route('articles.index')),
                classifieds: @json(route('classifieds.index')),
                advertise: @json(route('advertise.index')),
            };

            const typeSelect = document.querySelector('[data-attachment-type]');
            const itemSelect = document.querySelector('[data-attachment-id]');
            const applyButton = document.querySelector('[data-attachment-apply]');
            const actionPreset = document.querySelector('[data-action-preset]');
            const preview = document.querySelector('[data-attachment-preview]');
            const previewTitle = document.querySelector('[data-attachment-preview-title]');
            const previewMeta = document.querySelector('[data-attachment-preview-meta]');
            const previewUrl = document.querySelector('[data-attachment-preview-url]');
            const urlInput = document.getElementById('url');
            const imageInput = document.getElementById('image');
            const titleInput = document.getElementById('title');
            const bodyInput = document.getElementById('body');
            const action1Title = document.getElementById('action_1_title');
            const action1Url = document.getElementById('action_1_url');
            const action2Title = document.getElementById('action_2_title');
            const action2Url = document.getElementById('action_2_url');

            const selectedAttachment = () => {
                const type = typeSelect?.value;
                const id = itemSelect?.value;
                return type && id ? presets[`${type}:${id}`] : null;
            };

            const filterItems = () => {
                const type = typeSelect?.value || '';
                Array.from(itemSelect?.options || []).forEach((option) => {
                    if (!option.dataset.type) return;
                    option.hidden = option.dataset.type !== type;
                });

                const selectedOption = itemSelect?.selectedOptions?.[0];
                if (selectedOption?.dataset.type && selectedOption.dataset.type !== type) {
                    itemSelect.value = '';
                }

                updatePreview();
            };

            const updatePreview = () => {
                const attachment = selectedAttachment();
                if (!attachment) {
                    preview.hidden = true;
                    return;
                }

                preview.hidden = false;
                previewTitle.textContent = attachment.label;
                previewMeta.textContent = attachment.meta || 'Ready to attach to this push.';
                previewUrl.textContent = attachment.url;
            };

            const fillFromAttachment = () => {
                const attachment = selectedAttachment();
                if (!attachment) return;

                urlInput.value = attachment.url;
                if (attachment.image && !imageInput.value) imageInput.value = attachment.image;
                if (!titleInput.value) titleInput.value = attachment.label.slice(0, 80);
                if (!bodyInput.value) bodyInput.value = `New on Lifeat: ${attachment.label}`.slice(0, 180);
                setAttachmentActions();
            };

            const setAttachmentActions = () => {
                const attachment = selectedAttachment();
                if (!attachment) return;

                action1Title.value = attachment.primaryAction || 'Open';
                action1Url.value = attachment.url;
                action2Title.value = attachment.secondaryAction || 'Explore';
                action2Url.value = {
                    listing: routes.directory,
                    event: routes.events,
                    voucher: routes.vouchers,
                    article: routes.articles,
                    classified: routes.classifieds,
                }[attachment.type] || routes.advertise;
            };

            const applyActionPreset = () => {
                const preset = actionPreset?.value;

                if (preset === 'attachment') {
                    setAttachmentActions();
                    return;
                }

                if (preset === 'marketing') {
                    action1Title.value = 'Learn more';
                    action1Url.value = urlInput.value || routes.directory;
                    action2Title.value = 'Advertise';
                    action2Url.value = routes.advertise;
                    return;
                }

                if (preset === 'browse') {
                    action1Title.value = 'Browse now';
                    action1Url.value = urlInput.value || routes.directory;
                    action2Title.value = 'View vouchers';
                    action2Url.value = routes.vouchers;
                    return;
                }

                if (preset === 'clear') {
                    action1Title.value = '';
                    action1Url.value = '';
                    action2Title.value = '';
                    action2Url.value = '';
                }
            };

            typeSelect?.addEventListener('change', filterItems);
            itemSelect?.addEventListener('change', updatePreview);
            applyButton?.addEventListener('click', fillFromAttachment);
            actionPreset?.addEventListener('change', applyActionPreset);
            filterItems();
        })();
    </script>
</x-app-layout>
