@php
    $askLifeLocale = app()->getLocale() === 'af' ? 'af' : 'en';
    $askLifeTexts = [
        'en' => [
            'askJimmy' => 'Ask Jimmy',
            'headerSubtitle' => 'Find local answers, actions, and the right Life@ page.',
            'greeting' => 'Hi, I am Jimmy. What should I help you do?',
            'questionLabel' => 'Question',
            'placeholder' => 'Try: tyre repair in Bethlehem, events this weekend, improve my listing',
            'askTitle' => 'Ask',
            'toggleVoice' => 'Toggle voice',
            'muteVoice' => 'Mute voice',
            'enableVoice' => 'Enable voice',
            'clearConversation' => 'Clear conversation',
            'closeJimmy' => 'Close Jimmy',
            'thinking' => 'Jimmy is thinking',
            'open' => 'Open',
            'source' => 'Source',
            'lifeSource' => 'Life@ source',
            'date' => 'Date',
            'value' => 'Value',
            'price' => 'Price',
            'status' => 'Status',
            'fullSearchTitle' => 'Full Life@ search',
            'fullSearchSummary' => 'Open the full results page for a wider scan.',
            'openSearch' => 'Open search',
            'listen' => 'Listen',
            'listenTitle' => 'Listen to this answer',
            'listenAria' => 'Listen to Jimmy read this answer',
            'loading' => 'Loading',
            'playingSaved' => 'Playing saved',
            'playing' => 'Playing',
            'tryAgain' => 'Try again',
            'voiceUnavailable' => 'Voice unavailable',
            'helpful' => 'Helpful',
            'notHelpful' => 'Needs work',
            'saved' => 'Saved',
            'feedbackFailed' => 'Could not save feedback',
            'fallbackAnswer' => 'I could not build an answer from Life@ sources yet.',
            'unavailable' => 'Jimmy is unavailable right now. Try the full search page.',
        ],
        'af' => [
            'askJimmy' => 'Vra vir Jimmy',
            'headerSubtitle' => 'Vind plaaslike antwoorde, aksies en die regte Life@ bladsy.',
            'greeting' => 'Hallo, ek is Jimmy. Waarmee moet ek jou help?',
            'questionLabel' => 'Vraag',
            'placeholder' => 'Probeer: bandherstelwerk in Bethlehem, geleenthede hierdie naweek, verbeter my listing',
            'askTitle' => 'Vra',
            'toggleVoice' => 'Skakel stem',
            'muteVoice' => 'Demp stem',
            'enableVoice' => 'Skakel stem aan',
            'clearConversation' => 'Maak gesprek skoon',
            'closeJimmy' => 'Maak Jimmy toe',
            'thinking' => 'Jimmy dink',
            'open' => 'Maak oop',
            'source' => 'Bron',
            'lifeSource' => 'Life@ bron',
            'date' => 'Datum',
            'value' => 'Waarde',
            'price' => 'Prys',
            'status' => 'Status',
            'fullSearchTitle' => 'Volledige Life@ soektog',
            'fullSearchSummary' => 'Maak die volledige resultatebladsy oop vir n wyer soektog.',
            'openSearch' => 'Maak soektog oop',
            'listen' => 'Luister',
            'listenTitle' => 'Luister na hierdie antwoord',
            'listenAria' => 'Luister hoe Jimmy hierdie antwoord lees',
            'loading' => 'Laai',
            'playingSaved' => 'Speel gestoorde klank',
            'playing' => 'Speel',
            'tryAgain' => 'Probeer weer',
            'voiceUnavailable' => 'Stem is nie beskikbaar nie',
            'helpful' => 'Nuttig',
            'notHelpful' => 'Werk nodig',
            'saved' => 'Gestoor',
            'feedbackFailed' => 'Kon nie terugvoer stoor nie',
            'fallbackAnswer' => 'Ek kon nog nie n antwoord uit Life@ bronne bou nie.',
            'unavailable' => 'Jimmy is nou nie beskikbaar nie. Probeer die volledige soekbladsy.',
        ],
    ][$askLifeLocale];
@endphp

<div
    class="ask-life-widget"
    data-ask-life
    data-endpoint="{{ route('ask-life.store') }}"
    data-feedback-endpoint="{{ route('ask-life.feedback') }}"
    data-speak-endpoint="{{ route('ask-life.speak') }}"
    data-locale="{{ $askLifeLocale }}"
>
    <button type="button" class="ask-life-fab" data-ask-life-toggle aria-expanded="false" aria-controls="ask-life-panel" title="{{ $askLifeTexts['askJimmy'] }}">
        <x-icon name="sparkles" class="w-5 h-5" />
        <span>{{ $askLifeTexts['askJimmy'] }}</span>
    </button>

    <section id="ask-life-panel" class="ask-life-panel" data-ask-life-panel hidden aria-label="{{ $askLifeTexts['askJimmy'] }}">
        <div class="ask-life-head">
            <div>
                <strong>Jimmy</strong>
                <p>{{ $askLifeTexts['headerSubtitle'] }}</p>
            </div>
            <div class="ask-life-head-actions">
                <button type="button" class="ask-life-icon-btn" data-ask-life-voice-toggle aria-label="{{ $askLifeTexts['toggleVoice'] }}" title="{{ $askLifeTexts['toggleVoice'] }}">
                    <span data-voice-on><x-icon name="volume" class="w-4 h-4" /></span>
                    <span data-voice-off hidden><x-icon name="volume-off" class="w-4 h-4" /></span>
                </button>
                <button type="button" class="ask-life-icon-btn" data-ask-life-clear aria-label="{{ $askLifeTexts['clearConversation'] }}" title="{{ $askLifeTexts['clearConversation'] }}">
                    <x-icon name="trash" class="w-4 h-4" />
                </button>
                <button type="button" class="ask-life-close" data-ask-life-toggle aria-label="{{ $askLifeTexts['closeJimmy'] }}">
                    <x-icon name="x" class="w-5 h-5" />
                </button>
            </div>
        </div>

        <div class="ask-life-messages" data-ask-life-messages>
            <div class="ask-life-message ask-life-message-bot">{{ $askLifeTexts['greeting'] }}</div>
        </div>

        <form class="ask-life-form" data-ask-life-form>
            <label class="sr-only" for="ask-life-question">{{ $askLifeTexts['questionLabel'] }}</label>
            <textarea id="ask-life-question" data-ask-life-question rows="2" maxlength="500" placeholder="{{ $askLifeTexts['placeholder'] }}"></textarea>
            <button type="submit" class="ask-life-submit" title="{{ $askLifeTexts['askTitle'] }}">
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
        const toggleBtns = root.querySelectorAll('[data-ask-life-toggle]');
        const form = root.querySelector('[data-ask-life-form]');
        const question = root.querySelector('[data-ask-life-question]');
        const messages = root.querySelector('[data-ask-life-messages]');
        const voiceToggle = root.querySelector('[data-ask-life-voice-toggle]');
        const voiceOn = root.querySelector('[data-voice-on]');
        const voiceOff = root.querySelector('[data-voice-off]');
        const clearBtn = root.querySelector('[data-ask-life-clear]');
        const endpoint = root.dataset.endpoint;
        const feedbackEndpoint = root.dataset.feedbackEndpoint;
        const speakEndpoint = root.dataset.speakEndpoint;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const text = @json($askLifeTexts);
        const fallbackSearchUrl = @json(route('search.index'));

        const STORAGE_KEY = 'jimmy_chat_v1';
        const VOICE_KEY = 'jimmy_voice';
        const MAX_HISTORY = 8;

        let activeAudio = null;
        let conversationHistory = [];
        let voiceEnabled = storageGet(VOICE_KEY) !== 'false';

        function tr(key, fallback = '') {
            return text[key] || fallback || key;
        }

        function storageGet(key) {
            try { return localStorage.getItem(key); } catch (_) { return null; }
        }

        function storageSet(key, value) {
            try { localStorage.setItem(key, value); } catch (_) {}
        }

        function pageContext() {
            const heading = document.querySelector('main h1, [data-page-title], h1')?.textContent?.trim() || '';
            const path = window.location.pathname || '';

            return {
                page_type: detectPageType(path),
                page_title: document.title || '',
                page_heading: heading,
                page_url: `${window.location.origin}${window.location.pathname}`,
                path,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'Africa/Johannesburg',
                local_time: new Date().toISOString(),
                locale: root.dataset.locale || document.documentElement.lang || navigator.language || 'en',
            };
        }

        function detectPageType(path) {
            const target = String(path || '').toLowerCase();
            if (target.includes('/account/listings')) return 'account_listing_workspace';
            if (target.includes('/account/advertising')) return 'account_advertising';
            if (target.includes('/account')) return 'account';
            if (target.includes('/directory/') && target !== '/directory') return 'business_detail';
            if (target.includes('/directory')) return 'directory';
            if (target.includes('/events/') && target !== '/events') return 'event_detail';
            if (target.includes('/events')) return 'events';
            if (target.includes('/articles/') && target !== '/articles') return 'article_detail';
            if (target.includes('/articles')) return 'articles';
            if (target.includes('/vouchers')) return 'vouchers';
            if (target.includes('/classifieds') || target.includes('/my-classifieds')) return 'classifieds';
            if (target.includes('/faults')) return 'faults';
            if (target.includes('/transport')) return 'transport';
            if (target.includes('/advertise')) return 'advertise';
            if (target.includes('/add-listing')) return 'add_listing';
            if (target.includes('/checkout') || target.includes('/basket')) return 'checkout';
            return 'general';
        }

        function syncVoiceUI() {
            if (!voiceToggle) return;
            if (voiceOn) voiceOn.hidden = !voiceEnabled;
            if (voiceOff) voiceOff.hidden = voiceEnabled;
            voiceToggle.title = voiceEnabled ? tr('muteVoice') : tr('enableVoice');
            voiceToggle.setAttribute('aria-label', voiceToggle.title);
            voiceToggle.classList.toggle('muted', !voiceEnabled);
            panel.classList.toggle('voice-off', !voiceEnabled);
        }

        voiceToggle?.addEventListener('click', () => {
            voiceEnabled = !voiceEnabled;
            storageSet(VOICE_KEY, voiceEnabled ? 'true' : 'false');
            if (!voiceEnabled && activeAudio) {
                activeAudio.pause();
                activeAudio = null;
            }
            syncVoiceUI();
        });

        const openPanel = () => {
            panel.hidden = false;
            root.querySelector('.ask-life-fab')?.setAttribute('aria-expanded', 'true');
            messages.scrollTop = messages.scrollHeight;
            setTimeout(() => question?.focus(), 40);
        };

        const closePanel = () => {
            panel.hidden = true;
            root.querySelector('.ask-life-fab')?.setAttribute('aria-expanded', 'false');
        };

        toggleBtns.forEach(btn => btn.addEventListener('click', () => {
            panel.hidden ? openPanel() : closePanel();
        }));

        function saveHistory() {
            storageSet(STORAGE_KEY, JSON.stringify(conversationHistory.slice(-(MAX_HISTORY * 2))));
        }

        function resetGreeting() {
            messages.replaceChildren();
            appendMessage(tr('greeting'), 'bot');
        }

        function loadHistory() {
            try {
                const parsed = JSON.parse(storageGet(STORAGE_KEY) || '[]');
                if (!Array.isArray(parsed) || parsed.length === 0) return;
                conversationHistory = parsed;
                for (const turn of conversationHistory) {
                    appendMessage(turn.content, turn.role === 'user' ? 'user' : 'bot');
                }
            } catch (_) {}
        }

        clearBtn?.addEventListener('click', () => {
            conversationHistory = [];
            saveHistory();
            resetGreeting();
        });

        function appendMessage(content, type) {
            const el = document.createElement('div');
            el.className = `ask-life-message ask-life-message-${type}`;
            el.textContent = content;
            messages.appendChild(el);
            messages.scrollTop = messages.scrollHeight;
            return el;
        }

        function appendTyping() {
            const el = document.createElement('div');
            el.className = 'ask-life-message ask-life-message-bot ask-life-typing';
            el.setAttribute('aria-label', tr('thinking'));
            el.setAttribute('aria-live', 'polite');
            for (let i = 0; i < 3; i++) el.appendChild(document.createElement('span'));
            messages.appendChild(el);
            messages.scrollTop = messages.scrollHeight;
            return el;
        }

        function resolveTyping(el, text) {
            el.classList.remove('ask-life-typing');
            el.removeAttribute('aria-live');
            el.removeAttribute('aria-label');
            el.replaceChildren();
            el.textContent = text;
        }

        function appendBubbleTools(bubble, answerText, data, questionText, context) {
            const tools = document.createElement('div');
            tools.className = 'ask-life-actions';

            appendAnswerActions(tools, data.answer_actions || []);
            appendSpeakButton(tools, answerText, data.locale || 'en');
            appendFeedbackButtons(tools, answerText, data, questionText, context);

            if (tools.childElementCount > 0) {
                bubble.appendChild(tools);
            }
        }

        function appendAnswerActions(container, actions) {
            (actions || []).slice(0, 4).forEach(action => {
                const a = document.createElement('a');
                a.href = action.url || '#';
                a.className = `ask-life-action-link ask-life-action-${action.kind || 'link'}`;
                a.textContent = action.label || tr('open');
                if (action.external) {
                    a.target = '_blank';
                    a.rel = 'noopener';
                }
                container.appendChild(a);
            });
        }

        function appendSpeakButton(container, answerText, locale) {
            if (!speakEndpoint || !answerText) return;

            const speakBtn = document.createElement('button');
            speakBtn.type = 'button';
            speakBtn.className = 'ask-life-speak';
            speakBtn.title = tr('listenTitle');
            speakBtn.setAttribute('aria-label', tr('listenAria'));
            speakBtn.textContent = tr('listen');

            speakBtn.addEventListener('click', async () => {
                speakBtn.disabled = true;
                speakBtn.textContent = tr('loading');
                try {
                    const res = await fetch(speakEndpoint, {
                        method: 'POST',
                        headers: jsonHeaders(),
                        body: JSON.stringify({ text: answerText, locale }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok || !data.audio_url) throw new Error(data.message || tr('voiceUnavailable'));
                    if (activeAudio) activeAudio.pause();
                    activeAudio = new Audio(data.audio_url);
                    speakBtn.textContent = data.cached ? tr('playingSaved') : tr('playing');
                    activeAudio.addEventListener('ended', () => { speakBtn.disabled = false; speakBtn.textContent = tr('listen'); }, { once: true });
                    activeAudio.addEventListener('error', () => { speakBtn.disabled = false; speakBtn.textContent = tr('tryAgain'); }, { once: true });
                    await activeAudio.play();
                } catch {
                    speakBtn.disabled = false;
                    speakBtn.textContent = tr('voiceUnavailable');
                    setTimeout(() => { speakBtn.textContent = tr('listen'); }, 2500);
                }
            });

            container.appendChild(speakBtn);
        }

        function appendFeedbackButtons(container, answerText, data, questionText, context) {
            const feedback = document.createElement('div');
            feedback.className = 'ask-life-feedback';

            const helpful = feedbackButton('helpful', '+', tr('helpful'));
            const notHelpful = feedbackButton('not_helpful', '-', tr('notHelpful'));

            helpful.addEventListener('click', () => submitFeedback(helpful, feedback, 'helpful', answerText, data, questionText, context));
            notHelpful.addEventListener('click', () => submitFeedback(notHelpful, feedback, 'not_helpful', answerText, data, questionText, context));

            feedback.append(helpful, notHelpful);
            container.appendChild(feedback);
        }

        function feedbackButton(rating, text, label) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `ask-life-feedback-btn ${rating === 'helpful' ? 'positive' : 'negative'}`;
            btn.title = label;
            btn.setAttribute('aria-label', label);
            btn.textContent = text;
            return btn;
        }

        async function submitFeedback(btn, wrap, rating, answerText, data, questionText, context) {
            wrap.querySelectorAll('button').forEach(button => button.disabled = true);
            wrap.querySelectorAll('.ask-life-feedback-btn').forEach(button => button.classList.remove('active'));
            btn.classList.add('active');

            if (!feedbackEndpoint) return;

            try {
                await fetch(feedbackEndpoint, {
                    method: 'POST',
                    headers: jsonHeaders(),
                    body: JSON.stringify({
                        rating,
                        question: questionText,
                        answer: answerText,
                        generation_id: data.generation_id || null,
                        intent: data.intent?.key || null,
                        source: data.source || null,
                        source_ids: (data.sources || []).map(source => source.id).filter(Boolean),
                        sources: (data.sources || []).slice(0, 10).map(source => ({
                            id: source.id,
                            type: source.type,
                            title: source.title,
                        })),
                        page_context: context,
                    }),
                });
                btn.title = tr('saved');
            } catch {
                wrap.querySelectorAll('button').forEach(button => button.disabled = false);
                btn.title = tr('feedbackFailed');
            }
        }

        function appendSourceCards(sources, searchUrl) {
            if ((!sources || !sources.length) && !searchUrl) return;

            const wrap = document.createElement('div');
            wrap.className = 'ask-life-sources';

            (sources || []).slice(0, 5).forEach(source => {
                const card = document.createElement('article');
                card.className = 'ask-life-source-card';

                const top = document.createElement('div');
                top.className = 'ask-life-source-top';

                const badge = document.createElement('span');
                badge.textContent = source.label || source.type || tr('source');
                top.appendChild(badge);

                if (source.location) {
                    const location = document.createElement('small');
                    location.textContent = source.location;
                    top.appendChild(location);
                }

                const title = document.createElement('strong');
                title.textContent = source.title || tr('lifeSource');

                const summary = document.createElement('p');
                summary.textContent = source.summary || '';

                card.append(top, title);
                if (summary.textContent) card.appendChild(summary);

                const meta = metaLine(source.meta || {});
                if (meta) {
                    const metaEl = document.createElement('div');
                    metaEl.className = 'ask-life-source-meta';
                    metaEl.textContent = meta;
                    card.appendChild(metaEl);
                }

                const actionWrap = document.createElement('div');
                actionWrap.className = 'ask-life-card-actions';
                (source.actions || []).slice(0, 4).forEach(action => actionWrap.appendChild(actionLink(action)));
                if (actionWrap.childElementCount > 0) card.appendChild(actionWrap);

                wrap.appendChild(card);
            });

            if (searchUrl) {
                wrap.appendChild(searchCard(searchUrl));
            }

            messages.appendChild(wrap);
            messages.scrollTop = messages.scrollHeight;
        }

        function metaLine(meta) {
            const parts = [];
            if (meta.business) parts.push(meta.business);
            if (meta.date) parts.push(`${tr('date')}: ${meta.date}`);
            if (meta.value) parts.push(`${tr('value')}: ${meta.value}`);
            if (meta.price) parts.push(`${tr('price')}: ${meta.price}`);
            if (meta.status && meta.status !== 'published') parts.push(`${tr('status')}: ${meta.status}`);
            if (Array.isArray(meta.categories) && meta.categories.length) parts.push(meta.categories.slice(0, 2).join(', '));
            return parts.slice(0, 3).join(' | ');
        }

        function searchCard(searchUrl) {
            const card = document.createElement('article');
            card.className = 'ask-life-source-card ask-life-source-search';
            const title = document.createElement('strong');
            title.textContent = tr('fullSearchTitle');
            const summary = document.createElement('p');
            summary.textContent = tr('fullSearchSummary');
            const actions = document.createElement('div');
            actions.className = 'ask-life-card-actions';
            actions.appendChild(actionLink({ label: tr('openSearch'), url: searchUrl, kind: 'search' }));
            card.append(title, summary, actions);
            return card;
        }

        function actionLink(action) {
            const a = document.createElement('a');
            a.href = action.url || '#';
            a.className = `ask-life-card-action ask-life-card-action-${action.kind || 'link'}`;
            a.textContent = action.label || tr('open');
            if (action.external) {
                a.target = '_blank';
                a.rel = 'noopener';
            }
            return a;
        }

        function appendFollowUps(questions) {
            if (!questions || !questions.length) return;
            const wrap = document.createElement('div');
            wrap.className = 'ask-life-follow-ups';

            questions.slice(0, 3).forEach(q => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ask-life-follow-up';
                btn.textContent = q;
                btn.addEventListener('click', () => {
                    question.value = q;
                    wrap.remove();
                    form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                });
                wrap.appendChild(btn);
            });

            messages.appendChild(wrap);
            messages.scrollTop = messages.scrollHeight;
        }

        function jsonHeaders() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            };
        }

        form?.addEventListener('submit', async event => {
            event.preventDefault();

            const text = question.value.trim();
            if (!text) return;

            messages.querySelectorAll('.ask-life-follow-ups').forEach(el => el.remove());

            appendMessage(text, 'user');
            question.value = '';
            question.disabled = true;

            const typing = appendTyping();
            const historyPayload = conversationHistory.slice(-(MAX_HISTORY * 2));
            const context = pageContext();
            conversationHistory.push({ role: 'user', content: text });

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: jsonHeaders(),
                    body: JSON.stringify({ question: text, history: historyPayload, context }),
                });

                const data = await res.json().catch(() => ({}));
                const answer = data.answer || tr('fallbackAnswer');

                resolveTyping(typing, answer);
                conversationHistory.push({ role: 'assistant', content: answer });
                saveHistory();

                appendBubbleTools(typing, answer, data, text, context);
                appendSourceCards(data.sources || [], data.search_url);
                appendFollowUps(data.follow_up_questions || []);
            } catch {
                resolveTyping(typing, tr('unavailable'));
                conversationHistory.pop();
                saveHistory();
                appendSourceCards([], fallbackSearchUrl);
            } finally {
                question.disabled = false;
                question.focus();
            }
        });

        syncVoiceUI();
        loadHistory();
    })();
</script>
