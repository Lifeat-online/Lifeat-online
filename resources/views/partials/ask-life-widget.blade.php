<div class="ask-life-widget" data-ask-life data-endpoint="{{ route('ask-life.store') }}" data-speak-endpoint="{{ route('ask-life.speak') }}">
    <button type="button" class="ask-life-fab" data-ask-life-toggle aria-expanded="false" aria-controls="ask-life-panel" title="Ask Jimmy">
        <x-icon name="sparkles" class="w-5 h-5" />
        <span>Ask Jimmy</span>
    </button>

    <section id="ask-life-panel" class="ask-life-panel" data-ask-life-panel hidden aria-label="Ask Jimmy">
        <div class="ask-life-head">
            <div>
                <strong>Jimmy</strong>
                <p>Businesses, articles, events, vouchers, classifieds, and faults.</p>
            </div>
            <button type="button" class="ask-life-close" data-ask-life-toggle aria-label="Close Jimmy">
                <x-icon name="x" class="w-5 h-5" />
            </button>
        </div>

        <div class="ask-life-messages" data-ask-life-messages>
            <div class="ask-life-message ask-life-message-bot">Hi, I am Jimmy. What should I find for you?</div>
        </div>

        <form class="ask-life-form" data-ask-life-form>
            <label class="sr-only" for="ask-life-question">Question</label>
            <textarea id="ask-life-question" data-ask-life-question rows="2" maxlength="500" placeholder="Mechanic in Harrismith, events this weekend, water fault in Bethlehem"></textarea>
            <button type="submit" class="ask-life-submit" title="Ask">
                <x-icon name="arrow-right" class="w-5 h-5" />
            </button>
        </form>
    </section>
</div>

<script>
    (() => {
        const root = document.querySelector('[data-ask-life]');
        if (!root) return;

        const panel = root.querySelector('[data-ask-life-panel]');
        const toggles = root.querySelectorAll('[data-ask-life-toggle]');
        const form = root.querySelector('[data-ask-life-form]');
        const question = root.querySelector('[data-ask-life-question]');
        const messages = root.querySelector('[data-ask-life-messages]');
        const endpoint = root.dataset.endpoint;
        const speakEndpoint = root.dataset.speakEndpoint;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        let activeAudio = null;

        const openPanel = () => {
            panel.hidden = false;
            root.querySelector('.ask-life-fab')?.setAttribute('aria-expanded', 'true');
            setTimeout(() => question?.focus(), 40);
        };

        const closePanel = () => {
            panel.hidden = true;
            root.querySelector('.ask-life-fab')?.setAttribute('aria-expanded', 'false');
        };

        const appendMessage = (content, type = 'bot') => {
            const bubble = document.createElement('div');
            bubble.className = `ask-life-message ask-life-message-${type}`;
            bubble.textContent = content;
            messages.appendChild(bubble);
            messages.scrollTop = messages.scrollHeight;
            return bubble;
        };

        const appendSources = (sources, searchUrl) => {
            if ((!sources || !sources.length) && !searchUrl) return;

            const wrap = document.createElement('div');
            wrap.className = 'ask-life-sources';

            (sources || []).slice(0, 5).forEach((source) => {
                const link = document.createElement('a');
                link.href = source.url;
                link.className = 'ask-life-source-link';
                const type = document.createElement('span');
                const title = document.createElement('strong');
                type.textContent = source.type || 'source';
                title.textContent = source.title || 'Life@ source';
                link.append(type, title);
                wrap.appendChild(link);
            });

            if (searchUrl) {
                const search = document.createElement('a');
                search.href = searchUrl;
                search.className = 'ask-life-source-link ask-life-source-search';
                const type = document.createElement('span');
                const title = document.createElement('strong');
                type.textContent = 'search';
                title.textContent = 'Open full search results';
                search.append(type, title);
                wrap.appendChild(search);
            }

            messages.appendChild(wrap);
            messages.scrollTop = messages.scrollHeight;
        };

        const appendSpeakButton = (bubble, answer, locale = 'en') => {
            if (!speakEndpoint || !answer) return;

            const actions = document.createElement('div');
            actions.className = 'ask-life-actions';

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ask-life-speak';
            button.title = 'Listen to this answer';
            button.setAttribute('aria-label', 'Listen to Jimmy read this answer');
            button.textContent = 'Listen';

            button.addEventListener('click', async () => {
                button.disabled = true;
                button.textContent = 'Preparing...';

                try {
                    const response = await fetch(speakEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ text: answer, locale }),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || !data.ok || !data.audio_url) {
                        throw new Error(data.message || 'Voice unavailable');
                    }

                    if (activeAudio) {
                        activeAudio.pause();
                    }

                    activeAudio = new Audio(data.audio_url);
                    button.textContent = data.cached ? 'Playing saved audio' : 'Playing';
                    activeAudio.addEventListener('ended', () => {
                        button.disabled = false;
                        button.textContent = 'Listen';
                    }, { once: true });
                    activeAudio.addEventListener('error', () => {
                        button.disabled = false;
                        button.textContent = 'Try again';
                    }, { once: true });
                    await activeAudio.play();
                } catch (error) {
                    button.disabled = false;
                    button.textContent = 'Voice unavailable';
                    setTimeout(() => {
                        button.textContent = 'Listen';
                    }, 2500);
                }
            });

            actions.appendChild(button);
            bubble.appendChild(actions);
            messages.scrollTop = messages.scrollHeight;
        };

        toggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
                panel.hidden ? openPanel() : closePanel();
            });
        });

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const text = question.value.trim();
            if (!text) return;

            appendMessage(text, 'user');
            question.value = '';
            question.disabled = true;
            const pending = appendMessage('Jimmy is checking Life@ sources...', 'bot');

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ question: text }),
                });

                const data = await response.json();
                pending.textContent = data.answer || 'I could not build an answer from Life@ sources yet.';
                appendSpeakButton(pending, data.answer || pending.textContent, data.locale || 'en');
                appendSources(data.sources || [], data.search_url);
            } catch (error) {
                pending.textContent = 'Jimmy is unavailable right now. Try the full search page.';
                appendSources([], '{{ route('search.index') }}');
            } finally {
                question.disabled = false;
                question.focus();
            }
        });
    })();
</script>
