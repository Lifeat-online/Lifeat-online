@php
    $jimmyLocale = app()->getLocale() === 'af' ? 'af' : 'en';
    $jimmyTexts = [
        'en' => [
            'name' => 'Jimmy',
            'fabLabel' => 'Jimmy',
            'subtitle' => 'Editorial writing assistant',
            'greeting' => 'Hi, I am Jimmy, the Life@ editorial assistant. I can help write article drafts, summarise research, and manage editorial tasks.',
            'placeholder' => 'Ask Jimmy to help with editorial tasks...',
            'thinking' => 'Jimmy is thinking',
            'close' => 'Close Jimmy',
            'clear' => 'Clear conversation',
            'unavailable' => 'Jimmy is unavailable right now.',
        ],
        'af' => [
            'name' => 'Jimmy',
            'fabLabel' => 'Jimmy',
            'subtitle' => 'Redaksionele skryf-assistent',
            'greeting' => 'Hallo, ek is Jimmy, die Life@ redaksionele assistent. Ek kan help met artikels skryf, navorsing opsom, en redaksionele take bestuur.',
            'placeholder' => 'Vra Jimmy om te help met redaksionele take...',
            'thinking' => 'Jimmy dink',
            'close' => 'Maak Jimmy toe',
            'clear' => 'Maak gesprek skoon',
            'unavailable' => 'Jimmy is nou nie beskikbaar nie.',
        ],
    ][$jimmyLocale];
@endphp

<div class="fixed bottom-24 right-6 z-50" data-jimmy data-endpoint="{{ route('admin.jimmy.chat') }}">
    <button type="button" class="flex items-center gap-2 rounded-full bg-gradient-to-br from-amber-600 to-orange-700 px-5 py-3 text-sm font-semibold text-white shadow-lg hover:from-amber-500 hover:to-orange-600" data-jimmy-toggle aria-expanded="false" title="{{ $jimmyTexts['fabLabel'] }}">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        <span>{{ $jimmyTexts['fabLabel'] }}</span>
    </button>

    <section class="absolute bottom-16 right-0 hidden w-96 rounded-xl bg-white shadow-2xl ring-1 ring-black/5" data-jimmy-panel aria-label="{{ $jimmyTexts['name'] }}">
        <div class="flex items-center justify-between rounded-t-xl bg-gradient-to-br from-amber-600 to-orange-700 px-4 py-3 text-white">
            <div>
                <strong class="text-sm">{{ $jimmyTexts['name'] }}</strong>
                <p class="text-xs text-amber-100">{{ $jimmyTexts['subtitle'] }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="rounded p-1 hover:bg-white/20" data-jimmy-clear title="{{ $jimmyTexts['clear'] }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <button type="button" class="rounded p-1 hover:bg-white/20" data-jimmy-toggle title="{{ $jimmyTexts['close'] }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div class="flex h-80 flex-col gap-3 overflow-y-auto p-4 text-sm" data-jimmy-messages>
            <div class="rounded-lg bg-amber-50 p-3 text-amber-900">{{ $jimmyTexts['greeting'] }}</div>
        </div>

        <form class="flex items-end gap-2 border-t border-gray-100 p-3" data-jimmy-form>
            <textarea rows="2" class="min-h-[2.5rem] flex-1 resize-none rounded-lg border-gray-200 text-sm focus:border-amber-500 focus:ring-amber-500" data-jimmy-question placeholder="{{ $jimmyTexts['placeholder'] }}"></textarea>
            <button type="submit" class="rounded-lg bg-amber-600 p-2.5 text-white hover:bg-amber-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
            </button>
        </form>
    </section>
</div>

<script>
(() => {
    const root = document.querySelector('[data-jimmy]');
    if (!root) return;
    const panel = root.querySelector('[data-jimmy-panel]');
    const toggle = root.querySelector('[data-jimmy-toggle]');
    const form = root.querySelector('[data-jimmy-form]');
    const question = root.querySelector('[data-jimmy-question]');
    const messages = root.querySelector('[data-jimmy-messages]');
    const clear = root.querySelector('[data-jimmy-clear]');
    const endpoint = root.dataset.endpoint;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const text = @json($jimmyTexts);
    const STORAGE_KEY = 'jimmy_chat_v1';
    const MAX_HISTORY = 8;
    let history = [];

    function storageGet(key) { try { return localStorage.getItem(key); } catch { return null; } }
    function storageSet(key, v) { try { localStorage.setItem(key, v); } catch {} }

    function appendMsg(content, role) {
        const el = document.createElement('div');
        el.className = `rounded-lg p-3 ${role === 'user' ? 'ml-8 bg-indigo-50 text-indigo-900' : 'bg-amber-50 text-amber-900'}`;
        el.textContent = content;
        messages.appendChild(el);
        messages.scrollTop = messages.scrollHeight;
        return el;
    }

    function appendTyping() {
        const el = document.createElement('div');
        el.className = 'rounded-lg bg-amber-50 p-3 text-amber-900';
        el.textContent = text.thinking + '...';
        messages.appendChild(el);
        messages.scrollTop = messages.scrollHeight;
        return el;
    }

    function loadHistory() {
        try {
            const parsed = JSON.parse(storageGet(STORAGE_KEY) || '[]');
            if (!Array.isArray(parsed) || !parsed.length) return;
            history = parsed;
            history.forEach(t => appendMsg(t.content, t.role));
        } catch {}
    }

    function saveHistory() { storageSet(STORAGE_KEY, JSON.stringify(history.slice(-(MAX_HISTORY * 2)))); }

    toggle.addEventListener('click', () => {
        panel.classList.toggle('hidden');
        toggle.setAttribute('aria-expanded', !panel.classList.contains('hidden'));
    });

    clear.addEventListener('click', () => {
        history = [];
        saveHistory();
        messages.replaceChildren();
        appendMsg(text.greeting, 'assistant');
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const q = question.value.trim();
        if (!q) return;
        appendMsg(q, 'user');
        question.value = '';
        question.disabled = true;
        const typing = appendTyping();
        history.push({ role: 'user', content: q });
        try {
            const res = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ message: q, history: history.slice(-(MAX_HISTORY * 2)) }) });
            const data = await res.json();
            const answer = data.answer || data.message || 'Sorry, I could not process that.';
            typing.textContent = answer;
            history.push({ role: 'assistant', content: answer });
            saveHistory();
        } catch { typing.textContent = text.unavailable; history.pop(); } finally { question.disabled = false; question.focus(); }
    });

    loadHistory();
})();
</script>
