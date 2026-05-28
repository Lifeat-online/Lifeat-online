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
            <div class="ask-life-head-actions">
                <button type="button" class="ask-life-voice-toggle" data-ask-life-voice-toggle aria-label="Toggle voice">🔊</button>
                <button type="button" class="ask-life-clear" data-ask-life-clear aria-label="Clear conversation" title="Clear conversation">🗑</button>
                <button type="button" class="ask-life-close" data-ask-life-toggle aria-label="Close Jimmy">
                    <x-icon name="x" class="w-5 h-5" />
                </button>
            </div>
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

        const panel       = root.querySelector('[data-ask-life-panel]');
        const toggleBtns  = root.querySelectorAll('[data-ask-life-toggle]');
        const form        = root.querySelector('[data-ask-life-form]');
        const question    = root.querySelector('[data-ask-life-question]');
        const messages    = root.querySelector('[data-ask-life-messages]');
        const voiceToggle = root.querySelector('[data-ask-life-voice-toggle]');
        const clearBtn    = root.querySelector('[data-ask-life-clear]');
        const endpoint       = root.dataset.endpoint;
        const speakEndpoint  = root.dataset.speakEndpoint;
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const STORAGE_KEY     = 'jimmy_chat_v1';
        const VOICE_KEY       = 'jimmy_voice';
        const MAX_HISTORY     = 8; // user+assistant pairs

        let activeAudio        = null;
        let conversationHistory = [];
        let voiceEnabled       = localStorage.getItem(VOICE_KEY) !== 'false';

        // ── VOICE TOGGLE ──────────────────────────────────────────────────────

        function syncVoiceUI() {
            if (!voiceToggle) return;
            voiceToggle.textContent = voiceEnabled ? '🔊' : '🔇';
            voiceToggle.title       = voiceEnabled ? 'Mute voice' : 'Enable voice';
            voiceToggle.classList.toggle('muted', !voiceEnabled);
            panel.classList.toggle('voice-off', !voiceEnabled);
        }

        voiceToggle?.addEventListener('click', () => {
            voiceEnabled = !voiceEnabled;
            localStorage.setItem(VOICE_KEY, voiceEnabled ? 'true' : 'false');
            if (!voiceEnabled && activeAudio) { activeAudio.pause(); activeAudio = null; }
            syncVoiceUI();
        });

        // ── PANEL OPEN / CLOSE ────────────────────────────────────────────────

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

        // ── HISTORY PERSISTENCE ───────────────────────────────────────────────

        function saveHistory() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(
                    conversationHistory.slice(-(MAX_HISTORY * 2))
                ));
            } catch (_) {}
        }

        function loadHistory() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) return;
                const parsed = JSON.parse(raw);
                if (!Array.isArray(parsed) || parsed.length === 0) return;
                conversationHistory = parsed;
                for (const turn of conversationHistory) {
                    const el = document.createElement('div');
                    el.className = `ask-life-message ask-life-message-${turn.role === 'user' ? 'user' : 'bot'}`;
                    el.textContent = turn.content;
                    messages.appendChild(el);
                }
                messages.scrollTop = messages.scrollHeight;
            } catch (_) {}
        }

        clearBtn?.addEventListener('click', () => {
            conversationHistory = [];
            saveHistory();
            messages.innerHTML = '<div class="ask-life-message ask-life-message-bot">Hi, I am Jimmy. What should I find for you?</div>';
        });

        // ── DOM HELPERS ───────────────────────────────────────────────────────

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
            el.setAttribute('aria-label', 'Jimmy is thinking');
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
            while (el.firstChild) el.removeChild(el.firstChild);
            el.textContent = text;
        }

        function appendSources(sources, searchUrl) {
            if ((!sources || !sources.length) && !searchUrl) return;
            const wrap = document.createElement('div');
            wrap.className = 'ask-life-sources';

            (sources || []).slice(0, 5).forEach(source => {
                const a = document.createElement('a');
                a.href = source.url;
                a.className = 'ask-life-source-link';
                a.target = '_blank';
                a.rel = 'noopener';
                const badge = document.createElement('span');
                const title = document.createElement('strong');
                badge.textContent = source.type || 'source';
                title.textContent  = source.title || 'Life@ source';
                a.append(badge, title);
                wrap.appendChild(a);
            });

            if (searchUrl) {
                const a = document.createElement('a');
                a.href = searchUrl;
                a.className = 'ask-life-source-link ask-life-source-search';
                const badge = document.createElement('span');
                const title = document.createElement('strong');
                badge.textContent = 'search';
                title.textContent  = 'Open full search results';
                a.append(badge, title);
                wrap.appendChild(a);
            }

            messages.appendChild(wrap);
            messages.scrollTop = messages.scrollHeight;
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

        function appendActions(bubble, answerText, locale) {
            const actions = document.createElement('div');
            actions.className = 'ask-life-actions';

            // Speak button
            if (speakEndpoint && answerText) {
                const speakBtn = document.createElement('button');
                speakBtn.type = 'button';
                speakBtn.className = 'ask-life-speak';
                speakBtn.title = 'Listen to this answer';
                speakBtn.setAttribute('aria-label', 'Listen to Jimmy read this answer');
                speakBtn.textContent = '🔊 Listen';

                speakBtn.addEventListener('click', async () => {
                    speakBtn.disabled = true;
                    speakBtn.textContent = 'Loading...';
                    try {
                        const res = await fetch(speakEndpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({ text: answerText, locale }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.ok || !data.audio_url) throw new Error(data.message || 'Voice unavailable');
                        if (activeAudio) activeAudio.pause();
                        activeAudio = new Audio(data.audio_url);
                        speakBtn.textContent = data.cached ? '🔊 Playing saved' : '🔊 Playing';
                        activeAudio.addEventListener('ended',  () => { speakBtn.disabled = false; speakBtn.textContent = '🔊 Listen'; }, { once: true });
                        activeAudio.addEventListener('error',  () => { speakBtn.disabled = false; speakBtn.textContent = 'Try again';  }, { once: true });
                        await activeAudio.play();
                    } catch {
                        speakBtn.disabled = false;
                        speakBtn.textContent = 'Voice unavailable';
                        setTimeout(() => { speakBtn.textContent = '🔊 Listen'; }, 2500);
                    }
                });

                actions.appendChild(speakBtn);
            }

            // Feedback thumbs
            const feedback = document.createElement('div');
            feedback.className = 'ask-life-feedback';

            const makeThumb = (emoji, label, cls) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `ask-life-feedback-btn ${cls}`;
                btn.title = label;
                btn.setAttribute('aria-label', label);
                btn.textContent = emoji;
                btn.addEventListener('click', () => {
                    feedback.querySelectorAll('.ask-life-feedback-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                });
                return btn;
            };

            feedback.append(makeThumb('👍', 'Helpful', 'positive'), makeThumb('👎', 'Not helpful', 'negative'));
            actions.appendChild(feedback);

            bubble.appendChild(actions);
            messages.scrollTop = messages.scrollHeight;
        }

        // ── FORM SUBMIT ───────────────────────────────────────────────────────

        form?.addEventListener('submit', async event => {
            event.preventDefault();

            const text = question.value.trim();
            if (!text) return;

            // Remove stale follow-up chips before new question
            messages.querySelectorAll('.ask-life-follow-ups').forEach(el => el.remove());

            appendMessage(text, 'user');
            question.value   = '';
            question.disabled = true;

            const typing = appendTyping();

            // Snapshot history to send (before appending current user turn)
            const historyPayload = conversationHistory.slice(-(MAX_HISTORY * 2));

            // Push user turn into local history
            conversationHistory.push({ role: 'user', content: text });

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ question: text, history: historyPayload }),
                });

                const data   = await res.json();
                const answer = data.answer || 'I could not build an answer from Life@ sources yet.';

                resolveTyping(typing, answer);

                // Record assistant turn and persist
                conversationHistory.push({ role: 'assistant', content: answer });
                saveHistory();

                appendActions(typing, answer, data.locale || 'en');
                appendSources(data.sources || [], data.search_url);
                appendFollowUps(data.follow_up_questions || []);

            } catch {
                resolveTyping(typing, 'Jimmy is unavailable right now. Try the full search page.');
                conversationHistory.pop(); // drop the failed user turn
                saveHistory();
                appendSources([], '{{ route('search.index') }}');
            } finally {
                question.disabled = false;
                question.focus();
            }
        });

        // ── BOOT ─────────────────────────────────────────────────────────────

        syncVoiceUI();
        loadHistory();
    })();
</script>
