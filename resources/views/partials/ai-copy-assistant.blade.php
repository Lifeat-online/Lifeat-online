@php
    $mode = $mode ?? 'ad';
    $endpoint = $endpoint ?? '#';
    $heading = $heading ?? 'AI Copy Assistant';
    $description = $description ?? 'Generate a draft from the selected listing, current fields, and rough notes.';
    $placeholder = $placeholder ?? 'Add the offer, goal, audience, tone, or missing context.';
@endphp

<div
    class="rounded-lg border border-indigo-200 bg-indigo-50 p-4"
    data-ai-copy-assistant
    data-ai-copy-endpoint="{{ $endpoint }}"
    data-ai-copy-mode="{{ $mode }}"
>
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <h3 class="text-sm font-semibold text-indigo-950">{{ $heading }}</h3>
            <p class="mt-1 text-sm text-indigo-800">{{ $description }}</p>
        </div>
        <button type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white" data-ai-copy-generate>
            Generate draft
        </button>
    </div>

    <div class="mt-3">
        <label class="block text-sm font-medium text-indigo-950">Rough notes</label>
        <textarea rows="3" class="mt-1 block w-full rounded-md border-indigo-200 text-sm" data-ai-copy-notes placeholder="{{ $placeholder }}"></textarea>
    </div>

    <p class="mt-3 text-sm text-indigo-800" data-ai-copy-status></p>
</div>

@once
    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            const setField = (form, name, value) => {
                if (value === undefined || value === null || value === '') return;
                const field = form.querySelector(`[name="${name}"]`);
                if (field) field.value = value;
            };

            const setFieldIfBlank = (form, name, value) => {
                const field = form.querySelector(`[name="${name}"]`);
                if (!field || field.value) return;
                setField(form, name, value);
            };

            const firstOption = (suggestion) => Array.isArray(suggestion?.options) ? suggestion.options[0] : null;

            const fillSuggestion = (form, mode, suggestion) => {
                const option = firstOption(suggestion);

                if (mode === 'ad') {
                    setField(form, 'title', suggestion.campaign_title);
                    setField(form, 'headline', suggestion.headline);
                    setField(form, 'body', suggestion.body);
                    return;
                }

                if (mode === 'push') {
                    setField(form, 'title', suggestion.campaign_title || option?.title);
                    setField(form, 'headline', suggestion.headline || option?.title);
                    setField(form, 'message', suggestion.message || option?.body);
                    return;
                }

                if (mode === 'voucher') {
                    setField(form, 'title', suggestion.title);
                    setField(form, 'description', suggestion.description);

                    const terms = [suggestion.terms, suggestion.redemption_instructions]
                        .filter((value) => typeof value === 'string' && value.trim() !== '')
                        .join("\n\n");
                    setField(form, 'terms', terms);
                    return;
                }

                if (mode === 'event') {
                    setField(form, 'title', suggestion.title);
                    setFieldIfBlank(form, 'slug', suggestion.suggested_slug);
                    setField(form, 'excerpt', suggestion.excerpt);
                    setField(form, 'description', suggestion.description);
                    setField(form, 'venue_name', suggestion.venue_name);
                    setField(form, 'city', suggestion.city);
                }
            };

            const formPayload = (form, notes) => {
                const payload = {};
                const formData = new FormData(form);

                formData.forEach((value, key) => {
                    if (key.startsWith('_') || value instanceof File) return;

                    if (key.endsWith('[]')) {
                        const arrayKey = key.slice(0, -2);
                        payload[arrayKey] = payload[arrayKey] || [];
                        payload[arrayKey].push(value);
                        return;
                    }

                    payload[key] = value;
                });

                payload.rough_notes = notes;

                return payload;
            };

            document.addEventListener('click', async (event) => {
                const button = event.target.closest('[data-ai-copy-generate]');
                if (!button) return;

                const root = button.closest('[data-ai-copy-assistant]');
                const form = button.closest('form');
                const status = root?.querySelector('[data-ai-copy-status]');
                const notes = root?.querySelector('[data-ai-copy-notes]')?.value || '';

                if (!root || !form || !status) return;

                button.disabled = true;
                status.textContent = 'Generating copy...';

                try {
                    const response = await fetch(root.dataset.aiCopyEndpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(formPayload(form, notes)),
                    });
                    const data = await response.json();

                    if (!response.ok || !data.ok || !data.suggestion) {
                        throw new Error(data.message || 'AI copy could not be generated.');
                    }

                    fillSuggestion(form, root.dataset.aiCopyMode, data.suggestion);
                    status.textContent = 'Draft added. Review and edit before saving.';
                } catch (error) {
                    status.textContent = error.message || 'AI copy could not be generated.';
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
@endonce
