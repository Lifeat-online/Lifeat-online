# Jimmy AI - Quick Reference Guide

## 🎯 What's New in Jimmy v3

Jimmy now has **8 major enhancements** that make conversations smarter, more interactive, and respect user permissions.

---

## 🗣️ Multi-Turn Conversations

**What it does:** Jimmy remembers what you talked about earlier in the conversation.

**Example:**
```
You: "Show me mechanics in Harrismith"
Jimmy: "I found AutoFix, QuickServe, and CarCare..."

You: "Which one is open on Saturdays?"
Jimmy: "From the mechanics I mentioned, AutoFix is open Saturdays 8am-1pm..."
```

**How it works:**
- Conversation history stored in your browser
- Survives page refreshes
- Up to 16 messages remembered (8 back-and-forth exchanges)
- Clear button to start fresh

---

## 💬 Follow-Up Suggestions

**What it does:** Jimmy suggests 1-3 related questions you might want to ask next.

**Example:**
After asking about restaurants, Jimmy might suggest:
- "Which restaurants have outdoor seating?"
- "Show me restaurants with vegetarian options"
- "Are any of these open on Sundays?"

**How to use:** Just click the chip and it auto-fills + submits!

---

## ⏳ Typing Animation

**What it does:** Animated dots show Jimmy is thinking (instead of boring text).

**What you'll see:** Three animated dots that pulse while waiting for a response.

---

## 👍👎 Feedback Buttons

**What it does:** Rate Jimmy's answers with thumbs-up or thumbs-down.

**Why it matters:** Helps improve Jimmy's responses over time.

**How to use:** Click 👍 if the answer helped, 👎 if it didn't.

---

## 🔊 Voice Toggle

**What it does:** Control whether Jimmy offers to read answers aloud.

**How to use:**
- Click 🔊 in the header to mute voice
- Icon changes to 🔇 when muted
- All "Listen" buttons disappear when muted
- Setting saved in your browser

**When to use it:** Mute voice when you're in a quiet place or just prefer reading.

---

## 🗑️ Clear Conversation

**What it does:** Wipe the entire conversation and start fresh.

**How to use:** Click the 🗑 button in the header.

**Why you'd use it:**
- Starting a completely new topic
- Privacy (removes all messages from browser storage)
- Conversation got too long/confusing

---

## 🔐 Role-Based Access

**What it does:** Jimmy shows you different information based on who you are.

### Public Users (Not Logged In)
- See only **published** content
- Businesses, events, articles, vouchers must be live/approved
- Can't see draft or pending items

### Staff Users
- See all **public content**
- Plus: your own **draft/pending** listings
- Plus: events and vouchers from your own listings
- Useful when checking your own unpublished content

### Writer Users
- See all **published articles**
- Plus: your own **draft articles**
- Useful for reviewing your work-in-progress

### Councillor Users
- See all **approved faults**
- Plus: **unapproved fault reports** (for moderation)

### Admin/Editor Users
- See **everything** (no restrictions)
- All drafts, pending items, unapproved content visible

**Why it matters:** Jimmy won't tell you about content you don't have permission to see. No more "I can't find that" when you ask about your own draft!

---

## 💾 Chat Persistence

**What it does:** Your conversation is automatically saved in your browser.

**What that means:**
- Refresh the page → conversation still there
- Close tab and reopen → conversation still there
- Clear browser data → conversation is lost

**Storage limits:**
- Max 16 messages saved (oldest messages dropped)
- Voice preference saved separately
- Private to your browser (not synced across devices)

---

## 🆘 Troubleshooting

### "Jimmy is unavailable right now"
- AI service might be down or rate-limited
- Try the full search page (link appears when Jimmy is down)

### Conversation history not loading
- Check if browser storage is enabled
- Try clearing Jimmy's history and starting fresh

### Voice not working
- Check if voice is enabled (🔊 icon)
- Check browser audio permissions
- Some browsers block auto-play audio

### Follow-up chips not appearing
- Jimmy decides when to suggest follow-ups (not every answer)
- They only appear when there are related questions

### Lost conversation after closing browser
- Conversation is stored per-browser
- Private/incognito mode doesn't save history
- Different devices have separate histories

---

## 📱 Mobile Tips

- FAB button text ("Ask Jimmy") hides on narrow screens
- Typing animation fits small screens
- Follow-up chips wrap to multiple lines
- Voice/clear buttons stay accessible

---

## 🎨 Visual Guide

```
┌─────────────────────────────────────┐
│ Jimmy                               │
│ Businesses, articles, events...     │
│                           🔊 🗑 ✕   │  ← Voice, Clear, Close
├─────────────────────────────────────┤
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ Hi, I am Jimmy. What should I   │ │  ← Jimmy's messages
│ │ find for you?                   │ │
│ └─────────────────────────────────┘ │
│                                     │
│                 ┌─────────────────┐ │
│                 │ Mechanic in     │ │  ← Your messages
│                 │ Harrismith      │ │
│                 └─────────────────┘ │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ • • •                           │ │  ← Typing animation
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─────────────────────────────────┐ │
│ │ I found AutoFix, QuickServe...  │ │  ← Answer
│ │                                 │ │
│ │ 🔊 Listen  👍 👎                │ │  ← Voice + Feedback
│ └─────────────────────────────────┘ │
│                                     │
│ ┌─ business ─────────────────────┐ │
│ │ AutoFix Motors                  │ │  ← Source cards
│ └─────────────────────────────────┘ │
│                                     │
│ ⎔ Open on Saturdays?               │  ← Follow-up chips
│ ⎔ Show me prices                   │
│                                     │
├─────────────────────────────────────┤
│ [                           ] [→]  │  ← Question input
└─────────────────────────────────────┘
```

---

## 🚀 Best Practices

### For Best Results:
1. **Ask follow-up questions** instead of repeating context
   - ❌ "Show me restaurants in Bethlehem with outdoor seating"
   - ✅ "Show me restaurants in Bethlehem" → "Which ones have outdoor seating?"

2. **Use the follow-up chips** when they match what you need
   - They're pre-formatted for better results

3. **Give feedback** so Jimmy improves
   - 👍 when you find what you need
   - 👎 when the answer misses the mark

4. **Clear conversation** when switching topics completely
   - Helps Jimmy focus on the new topic

5. **Check your role** if you can't find draft/pending items
   - Log in if you need to see your own content

### For Staff Users:
- Ask about your own listings by name or city
- Jimmy can now tell you about unpublished listings you own
- Useful for checking before going live

### For Writers:
- Ask "show me my draft about [topic]"
- Jimmy can find your unpublished articles

### For Admins:
- Ask about any content, published or not
- Use for quick content lookups during moderation

---

**Need help?** Just ask Jimmy: "What can you help me with?"
