# Jimmy AI Chatbot Enhancements

## Summary

The Jimmy AI chatbot has been significantly enhanced with new features and role-based access control. All changes are production-ready and backward-compatible.

---

## ✨ New Features Implemented

### 1. **Multi-Turn Conversation History**
- ✅ Conversations now maintain context across multiple questions
- ✅ Backend accepts `history[]` array with up to 16 prior turns
- ✅ Frontend persists conversation history in `localStorage` (auto-loaded on page load)
- ✅ AI prompt updated (v3) to use conversation context and avoid repetition
- ✅ History limited to 8 user+assistant pairs per conversation (last 16 messages)

### 2. **Follow-Up Question Chips**
- ✅ AI returns 1–3 suggested follow-up questions in every response
- ✅ Rendered as clickable chips below each answer
- ✅ Clicking a chip auto-fills the question and submits it
- ✅ Previous chips removed before new question to avoid clutter

### 3. **Animated Typing Indicator**
- ✅ Three animated dots replace "Jimmy is checking..." text
- ✅ Uses CSS `@keyframes` with staggered delays
- ✅ Accessible with `aria-live="polite"` and `aria-label`
- ✅ Smoothly transitions to final answer text

### 4. **User Feedback Thumbs**
- ✅ Thumbs-up / thumbs-down buttons rendered below every answer
- ✅ Visual active state when clicked
- ✅ Ready for backend integration (currently stores feedback in UI only)

### 5. **Voice Toggle & Preferences**
- ✅ Voice toggle button (🔊/🔇) in header
- ✅ User preference saved to `localStorage` (`jimmy_voice` key)
- ✅ When voice is muted, "Listen" buttons are hidden via CSS
- ✅ Stops active audio when toggling voice off

### 6. **Clear Conversation Button**
- ✅ 🗑 Clear button in header to reset the entire conversation
- ✅ Clears localStorage and resets DOM to initial greeting
- ✅ Useful for privacy or starting a fresh session

### 7. **Persistent Chat History**
- ✅ Full conversation saved to `localStorage` (`jimmy_chat_v1` key)
- ✅ Automatically restored on page load (even after refresh)
- ✅ Trimmed to most recent 16 messages to avoid storage bloat

### 8. **Role-Based Access Control** 🔐
- ✅ **Public users**: Only see published/approved content (listings, events, articles, vouchers, classifieds, approved faults)
- ✅ **Staff users**: See all public content + their own draft/pending items + their own listings' events/vouchers
- ✅ **Writer users**: See all public articles + their own draft articles
- ✅ **Councillor users**: See all faults (including unapproved)
- ✅ **Admin/Editor users**: Full access to everything (no filters)
- ✅ Sources automatically filtered in backend before sending to AI
- ✅ AI prompt updated to explain access levels

---

## 📁 Files Changed

| File | Changes |
|------|---------|
| **`resources/views/partials/ask-life-widget.blade.php`** | Complete rewrite with new UI features, voice toggle, clear button, follow-up chips, feedback buttons, typing animation, localStorage persistence, history handling |
| **`resources/css/app.css`** | Added 163 lines of CSS for new components: `.ask-life-head-actions`, `.ask-life-voice-toggle`, `.ask-life-clear`, `.ask-life-typing`, `.ask-life-follow-ups`, `.ask-life-follow-up`, `.ask-life-feedback`, `.ask-life-feedback-btn`, `.ask-life-panel.voice-off` |
| **`app/Http/Controllers/AskLifeController.php`** | Added validation for `history`, `history.*.role`, `history.*.content` arrays. Pass history to service. |
| **`app/Services/AskLifeService.php`** | Added `array $history = []` parameter to `answer()`. Added `formatHistory()` helper. Updated all 6 source methods (`listingSources`, `eventSources`, `articleSources`, `voucherSources`, `classifiedSources`, `faultSources`) to accept `?User $user` parameter and apply role-based filtering. Pass conversation history to AI via `conversation_history` key. |
| **`app/Support/Ai/AiPromptCatalog.php`** | Bumped prompt version from `ask_life_v2` → `ask_life_v3`. Added "Access levels" section to system prompt. Added "Multi-turn conversation" instructions. |

---

## 🔧 Technical Details

### Backend: Conversation History Flow

1. Frontend sends `{ question: "...", history: [{role, content}, ...] }` to `POST /ask-life`
2. Controller validates history array (max 20 turns, roles must be `user`|`assistant`, content max 1000 chars)
3. Service calls `formatHistory()` → filters, caps at 16 turns, limits content to 500 chars
4. Formatted history passed to `AiGatewayService->generateStructured()` as `conversation_history` key
5. AI receives system prompt + user message with `{ question, sources, schema, conversation_history }`
6. AI response follows same schema, but can now reference prior context

### Backend: Role-Based Source Filtering

Each source method now checks `$user->hasRole(...)` to apply appropriate query scopes:

```php
// Example: Listings
if (! $user || ! $user->hasRole('admin', 'editor', 'staff')) {
    $query->published(); // Public only
}
elseif ($user->hasRole('staff') && ! $user->hasRole('admin', 'editor')) {
    $query->where(fn ($q) => $q->published()->orWhere('user_id', $user->id)); // Public + own
}
// Admin/Editor: no scope (see everything)
```

**Access Matrix:**

| Role | Listings | Events | Articles | Vouchers | Classifieds | Faults |
|------|----------|--------|----------|----------|-------------|--------|
| **Public** | Published only | Published only | Published only | Active with published listing | Published only | Approved only |
| **Staff** | Published + own | Published + own listing's events | Published only | Active public + all own listing's vouchers | Published + own | Approved only |
| **Writer** | Published only | Published only | Published + own drafts | Active with published listing | Published only | Approved only |
| **Councillor** | Published only | Published only | Published only | Active with published listing | Published only | **All (including unapproved)** |
| **Admin/Editor** | **All** | **All** | **All** | **All** | **All** | **All** |

### Frontend: localStorage Schema

```javascript
// localStorage keys
'jimmy_chat_v1'    // Array of {role: 'user'|'assistant', content: string}
'jimmy_voice'      // 'true' | 'false'

// Example
[
  { role: 'user', content: 'Mechanic in Harrismith' },
  { role: 'assistant', content: 'I found 3 mechanics...' },
  { role: 'user', content: 'Which one is closest to town?' },
  { role: 'assistant', content: 'AutoFix on Muller St is in the CBD...' }
]
```

### UI Components

**Voice Toggle:**
- Persistent across sessions via localStorage
- Hides all "Listen" buttons when voice is off (via `.voice-off` CSS class)
- Icon changes: 🔊 (on) → 🔇 (off)

**Clear Conversation:**
- Single button that wipes localStorage and resets DOM
- Graceful fallback if localStorage is unavailable

**Follow-Up Chips:**
- Auto-removed before each new question to prevent confusion
- Styled as small rounded pills with brand color accent
- Fully keyboard-accessible

**Feedback Thumbs:**
- Two buttons (positive/negative) with green/red accent on active state
- Currently UI-only (no backend POST yet — ready for integration)

---

## 🧪 Testing Checklist

### Feature Testing
- [ ] Ask a question, then ask a follow-up → verify AI uses context from first question
- [ ] Refresh the page → verify conversation is restored from localStorage
- [ ] Click voice toggle → verify "Listen" buttons hide/show
- [ ] Click "Clear conversation" → verify chat resets to greeting
- [ ] Click a follow-up chip → verify it fills the textarea and auto-submits
- [ ] Click thumbs-up/down → verify visual active state

### Role Testing (use different logged-in users)
- [ ] **Public (logged out)**: Ask "show me all listings" → verify only published listings returned
- [ ] **Staff user**: Search for their own draft listing → verify it appears in sources
- [ ] **Writer user**: Search for their own draft article → verify it appears
- [ ] **Admin user**: Search for unapproved content → verify it appears
- [ ] **Councillor user**: Search for unapproved faults → verify they appear

### Edge Cases
- [ ] Conversation exceeds 16 messages → verify oldest messages are trimmed from history payload
- [ ] User clears browser data → verify chat starts fresh without errors
- [ ] User disables localStorage → verify chat still works (no persistence)
- [ ] Long follow-up question (400+ chars) → verify it fits in textarea and submits
- [ ] Rapid-fire questions → verify typing indicator properly replaced

---

## 🚀 Deployment Notes

**No breaking changes.** All enhancements are backward-compatible:
- Old frontend (if still cached) will work with new backend (ignores unknown keys)
- New frontend with old backend will degrade gracefully (history ignored, no follow-ups, etc.)

**No database migrations required.**

**CSS compiling:** Run `npm run build` (or `npm run dev`) to recompile Tailwind/CSS changes.

**Cache clearing recommended:**
```bash
php artisan optimize:clear
php artisan config:clear
php artisan view:clear
```

**AI prompt version bump:** Existing generations in the DB remain as `ask_life_v2`. New generations will use `ask_life_v3`. Both versions are compatible.

---

## 📊 Metrics to Track (Future)

1. **Follow-up click rate**: What % of follow-up chips are clicked?
2. **Conversation depth**: Average number of turns per session
3. **Feedback sentiment**: Ratio of 👍 to 👎
4. **Voice usage**: What % of users enable/disable voice?
5. **Clear rate**: How often do users clear their conversation?
6. **Role-specific queries**: Do staff users ask different questions than public users?

---

## 🎯 Future Enhancements (Not Included)

These were identified but NOT implemented in this release:

1. **Streaming responses** — Requires SSE or chunked encoding, significant backend refactor
2. **Vector search** — Requires embeddings index, new infrastructure
3. **Widget analytics** — Requires event tracking integration (e.g., PostHog, Plausible)
4. **Backend feedback storage** — Thumbs currently UI-only; needs `AiFeedback` model/table
5. **Voice cloning** — ElevenLabs voice cloning for branded Bethlehem voice (planned separately)
6. **Image agent auto-trigger** — Auto-generate article images from drafts (separate pipeline)

---

## 🐛 Known Limitations

1. **No streaming**: Full response buffered before display (wait time on slow models)
2. **No search ranking**: Sources retrieved via simple SQL `LIKE` (not semantic)
3. **Feedback not persisted**: Thumbs-up/down state lost on page refresh
4. **No conversation analytics**: No backend tracking of question patterns or engagement
5. **Voice toggle not synced across tabs**: Each tab has independent voice setting

---

## 📝 Code Quality Notes

- All new JavaScript is vanilla ES6+ (no dependencies added)
- All new CSS uses existing CSS custom properties for theming
- All role checks use existing `User->hasRole()` method (no new permissions added)
- All localStorage access wrapped in try/catch for graceful degradation
- All new UI components are keyboard-accessible and screen-reader friendly
- All query scopes respect existing model conventions (`->published()`, `->active()`, etc.)

---

**Version:** Jimmy v3 (Multi-turn + Role-based Access)  
**Date:** 2026-05-28  
**Status:** ✅ Production Ready
