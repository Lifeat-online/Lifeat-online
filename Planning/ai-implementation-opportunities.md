# Life@ AI Implementation Opportunities And Roadmap

Status: Phase 1 foundation implemented; Jimmy chat, commercial copy, AI article translation, AI event description, research collector, Editorial Agent brief queue, Jimmy draft-writing stage, and Image Agent implemented
Last updated: 2026-05-23

## Purpose

This document turns the Life@ AI opportunity list into an implementation-ready roadmap. It keeps the original module-by-module ideas, adds additional product and engineering opportunities, and groups the work into realistic phases.

Life@ already has the right ingredients for useful AI:
- Local content: articles, categories, tags, authors, and locations.
- Commercial data: business listings, packages, vouchers, adverts, push campaigns, invoices, payments, and subscriptions.
- Civic data: fault reports, councillor areas, fault photos, map data, and status workflows.
- Transport data: driver duty sessions, vehicle types, requests, fare estimates, and live tracking foundations.
- Community data: classifieds, events, user accounts, notification logs, and staff/writer workflows.

The strongest AI direction is not "AI for decoration". It should reduce admin work, improve local discovery, help low-tech users complete forms, protect community trust, and make the platform feel alive in English and Afrikaans.

## Effort Key

- Easy: uses existing data and one AI call, with simple review controls.
- Medium: needs new database fields, workflow changes, queues, or moderation.
- Complex: needs embeddings, retrieval, realtime data, analytics, vision, personalisation, or multi-step orchestration.

## Product Principles

1. Keep humans in control for public publishing, municipal escalation, paid advertising, and moderation decisions.
2. Make bilingual output standard. English and Afrikaans should be generated together wherever the user sees AI text.
3. Use Life@ data first. AI should answer from listings, articles, events, classifieds, faults, vouchers, and packages before using outside knowledge.
4. Save AI output with provenance. Store provider, model, prompt type, source object, generated text, reviewer, and approval status.
5. Treat POPIA and trust as core requirements. Do not send unnecessary personal data to model providers.
6. Add AI in vertical slices. Each feature should produce a usable outcome, not only infrastructure.
7. Prefer staff-assisted AI first. Internal writer, sales, councillor, and admin tools are safer launch points than fully autonomous public-facing AI.

## Shared AI Foundation

Build these once, then reuse them across modules:

- AI provider settings in the Dev/admin area: provider, model, API key, timeout, enabled features, and test prompt.
- A central `AiGatewayService` that wraps provider calls, retries, logging, safety checks, and cost tracking.
- Prompt templates stored in code or database with version names such as `listing_description_v1`, `fault_category_v1`, and `article_translate_v1`.
- `ai_generations` table for audit logs: feature, source type, source id, prompt version, provider, model, input hash, output, language, status, cost estimate, and user id.
- Queue jobs for slower tasks like translation, weekly digests, article quality checks, duplicate detection, embeddings, and vision.
- Review UI components: accept, edit, regenerate, reject, and "why this suggestion?".
- Feature flags per module so AI can be switched on gradually.
- Optional embeddings index for semantic search, similar businesses, duplicate detection, chatbot retrieval, and content recommendations.

## Provider Strategy

Life@ should keep provider choice configurable so testing and production can diverge without rewriting feature code.

- Testing default: OpenRouter with free models where possible, because it makes model comparison cheap and fast while prompts are still changing.
- Production-ready direct providers: OpenAI, Anthropic, Google Gemini, Mistral, Groq, xAI, NVIDIA NIM, Perplexity, Together AI, Fireworks AI, Hugging Face, Azure OpenAI, and Cohere.
- Excluded by default for editorial/news workflows: DeepSeek, because unpublished local/community content raises privacy and jurisdiction concerns.
- Local or private deployment option: Ollama/local OpenAI-compatible endpoints for development, demos, or later private-hosted models.
- Provider settings should live in Dev/admin with provider, model, API key, base URL, status, and test prompt.
- Feature code should call `AiGatewayService` only. It should never know which provider is active.
- Each AI generation should store provider and model so quality, cost, and failures can be compared later.
- Model names must stay editable because provider catalogs change faster than application releases.

## Voice Layer Strategy

Recommendation: use ElevenLabs as the default speech provider for Jimmy, article audio snippets, and future voice-agent experiences.

Why ElevenLabs is the right default for Life@:
- Voice quality: Eleven v3 is positioned as the expressive/high-quality model and supports 70+ languages, including Afrikaans (`afr`).
- Afrikaans support: Afrikaans is a first-class supported language in the Eleven v3 language list, which matters for a Bethlehem/Eastern Free State audience.
- Real-time latency: Eleven Flash v2.5 is designed for real-time agents and lists ultra-low latency around 75ms before application and network latency.
- Voice cloning: ElevenLabs supports Instant Voice Cloning and Professional Voice Cloning, giving Life@ the option to create a recognisable local voice identity later.

Recommended model split:
- Spoken chat responses: Eleven Flash v2.5 for speed.
- Higher-quality article narration or promos: Eleven v3 or Multilingual v2/v3 depending on current pricing and audio quality tests.
- Local branded Jimmy voice: start with a standard ElevenLabs voice, then test a consented local Bethlehem voice clone once the chat experience proves useful.

Stack flow:

```text
User asks Jimmy
    -> Gemini/OpenRouter text response from Life@ sources
    -> Language detection: English = en, Afrikaans = af
    -> ElevenLabs text-to-speech
    -> Stream or return audio to browser
```

Implementation notes:
- Add a `VoiceGatewayService` rather than mixing audio logic into `AiGatewayService`.
- Store provider, model, voice id, language, input hash, character count, cost estimate, and cache key for each spoken response.
- Cache audio by `(voice_id, model, locale, text_hash)` so repeated common answers do not pay twice.
- Use browser autoplay rules correctly: only play audio after explicit user action.
- Always keep text visible; audio is an enhancement, not the only way to receive the answer.
- Detect output language from the model response and pass the matching ElevenLabs language code when the API/model requires it.
- Add a mute/voice toggle in the Jimmy widget before enabling audio by default.

Implementation checkpoint:
- `VoiceGatewayService` now handles speech providers separately from the text/image AI gateway.
- The Jimmy widget has a speaker button that calls `/ask-life/speak` after the user clicks, generates English or Afrikaans audio, and reuses cached audio for repeated answers.
- Dev/admin AI settings now include voice provider, API key, voice ID, English model/language, Afrikaans model/language, base URL, and output format.
- The AI tab includes a Voice Test panel so Jimmy's configured voice can be generated and played from the browser before wider rollout.
- NVIDIA Speech NIM is available as a testing provider for English/self-hosted voice experiments. Keep ElevenLabs as the default for production Afrikaans support.
- Audio generation is logged in `ai_generations` with feature key `ask_life_voice`, provider, model, locale, cache path, and character count.
- Jimmy is not just a search wrapper. His prompt now includes a character and integrity contract: warm, practical, conversational, honest about uncertainty, and careful not to invent facts outside supplied Life@ sources.
- Jimmy receives a small platform guide source map in addition to live public records, so he can help users choose the right Life@ workflow even when there is no exact listing, event, voucher, classified, article, or fault match yet.

AI operations checkpoint:
- `/admin/ai-operations` now gives the Dev owner a control room for AI generations, provider/model/status stats, errors, output previews, and retry actions where the original input payload is stored.
- New AI generations store `input_payload` and `retry_of_id`, making future replay, debugging, and cost analysis more reliable.
- Prompt templates can be overridden from the admin panel while keeping schemas fixed in code, so copy/style tuning does not require a deployment.
- Cost estimates are stored and displayed in rand. Provider rates remain configurable in USD and convert through `AI_COST_USD_TO_ZAR`, with the current planning default set to R16.46 per USD.
- Monthly AI budget limits are configurable in AI Operations. The panel shows warning and hard-stop status, and provider calls for non-exempt features are blocked once the monthly rand cap is reached and hard stop is enabled.

Voice cloning rules:
- Do not clone any community member, presenter, councillor, staff member, or public figure without explicit written consent.
- Keep signed consent and usage scope in admin records before activating a cloned voice.
- Label the assistant voice internally by voice id and consent owner.
- Avoid using cloned voices for news claims or official municipal-style announcements where users may confuse AI output with a real person.
- A local voice can be a strong differentiator, but it should launch only after the text assistant has guardrails, source links, and moderation in place.

Alternatives:

| Provider | Fit For Life@ | Afrikaans | Notes |
| --- | --- | --- | --- |
| ElevenLabs | Best default | Yes | Strong quality, low-latency Flash option, voice cloning path. |
| NVIDIA Speech NIM / Riva TTS | Testing/self-hosted | Not currently preferred | Useful for local GPU or hosted NIM experiments; strongest for English testing until Afrikaans is supported. |
| Google Cloud TTS / Chirp | Good backup | Good language coverage | Attractive if Life@ standardises on Google/Gemini infrastructure. |
| OpenAI TTS | Good English fallback | Needs testing | Useful if production AI moves heavily to OpenAI, but Afrikaans quality must be tested. |
| Deepgram Aura | Call-centre/realtime fallback | Needs testing | Strong realtime focus, but not the best default for Life@ bilingual community identity. |
| Amazon Polly | Reliability fallback | Limited for this use case | Mature infrastructure, but less compelling for expressive bilingual chat. |

Pricing assumptions:
- ElevenLabs API pricing changes by plan and model. The current planning assumption is roughly USD 0.05-0.06 per 1,000 characters for Flash/Turbo API TTS, subject to plan and provider updates.
- A 200-character spoken Jimmy response is therefore cents-level in ZAR, especially with response/audio caching.
- NVIDIA Speech NIM local/self-hosted testing can be configured at R0 per 1,000 characters in app cost tracking, with real GPU/server cost tracked separately.
- Re-check ElevenLabs pricing before enabling high-volume automatic narration, daily audio digests, or push-to-audio features.

## 1. Search - Make It Actually Intelligent

- Easy: Natural language search. Let users type "cheap plumber in Bethlehem open on Saturdays" and extract intent, location, category, budget, schedule, and urgency before querying existing listings.
- Easy: Cross-section unified answers. Classify a query such as "load shedding Bethlehem" and surface articles, electricians, fault reports, events, classifieds, and relevant notices in one result set.
- Medium: "Did you mean...?" and query correction. Handle misspellings, local place names, Afrikaans/English switching, and informal phrasing.
- Medium: Search result summaries. Add one-sentence AI summaries explaining why each result matched the query.
- Medium: Local synonym dictionary builder. Let staff review AI-suggested synonyms such as "bakkie", "LDV", "plumber", "loodgieter", "burst pipe", and "water leak".
- Complex: Semantic search index. Store embeddings for listings, articles, classifieds, events, vouchers, and fault categories so discovery works beyond exact keywords.
- Complex: "Near me and open now" ranking. Combine semantic intent, GPS, opening hours, active packages, and review quality into a stronger ranked result.

Implementation notes:
- Start by extending the existing `SearchController` flow with an intent extraction step.
- Keep the normal keyword search as fallback when the AI provider is offline.
- Return structured intent JSON from the model, not free-form text.

## 2. Articles - Writer Tools And Content Intelligence

- Easy: AI writing assistant for staff writers. Add an article editor sidebar where rough notes become a structured draft, headline options, intro paragraph, and suggested angle.
- Easy: Auto-translation English <-> Afrikaans. Translate articles so writers can write once and serve both audiences. Add an "AI-assisted translation" label for transparency.
- Easy: SEO meta generator. Generate meta title, description, social preview copy, and suggested article slug from the article body.
- Easy: Auto-tagging and categorisation. Extract people, places, organisations, topics, and suggested category/tag links.
- Medium: Story idea radar. Weekly AI summary of Eastern Free State leads from public sources, municipal notices, DA releases, community posts, and platform search trends.
- Medium: Article quality checker. Flag thin content, missing quotes, unsupported claims, weak headlines, duplicate story angles, and missing local context before publish.
- Medium: Quote and claim checklist. Let editors mark claims as verified, needs source, opinion, or public record before publishing.
- Medium: Article-to-push generator. Turn an article into short push notification options with tone, urgency, and action button suggestions.
- Medium: Related-content linker. Suggest existing articles, businesses, events, classifieds, and fault reports to link inside an article.
- Complex: Writer coaching dashboard. Track common rewrite reasons, publish conversion, word count quality, rejected drafts, and suggest training notes per writer.

Implementation notes:
- Use the existing writer workflow and article moderation path rather than creating a separate AI editor.
- Generated text should remain draft until a writer or editor accepts it.
- Translation can reuse the existing content translation model/service pattern.

## 2A. Full Editorial AI Pipeline

Pipeline:

```text
Research Agent -> Editorial Agent -> Jimmy (Reporter/Article Writer Agent) -> Image Agent -> Publish To Life@
```

Each stage is a separate concern and can be built incrementally. The goal is not instant auto-publishing. The goal is to turn local research into reviewed briefs, then reviewed article drafts, with editor control at every public-facing step.

### Stage 1: Research Agent

This is the hardest stage because social media scraping is heavily restricted. Start with reliable web/news sources before trying social platforms.

Web news sources:
- Google News RSS: free, no API key. Example query: `https://news.google.com/rss/search?q=Bethlehem+Free+State&hl=en-ZA`. It returns headlines and links.
- NewsAPI.org: free tier around 100 requests per day. Search by keyword, language, and country; returns article metadata.
- SerpAPI or Brave Search API: paid but affordable. Useful when the AI needs search-engine-style discovery instead of fixed feeds.
- Anthropic API with web search enabled: cleanest research path when available, because one API call can search and summarise.

Social media reality:
- Facebook Graph API is heavily restricted. It can usually read only public pages the app manages. Public group monitoring may need third-party RSS conversion.
- X/Twitter API is too expensive for a small platform unless there is a specific business case.
- Instagram has similar restrictions to Facebook.
- Practical workaround: create Google Alerts for phrases such as "Bethlehem Free State", "Dihlabeng", and "Eastern Free State news", then monitor the alert email inbox or RSS versions of those alerts.

Local-specific sources to monitor:
- Caxton / Bethlehem Express via `caxton.co.za`.
- Free State provincial government notices via `ofs.co.za` and official department sites.
- Dihlabeng Local Municipality website and official page.
- DA Free State press releases.
- SAPS Free State crime statistics and media releases.
- OFM Radio and other Eastern Free State coverage.

Research Agent output:
- Raw item title, source, source URL, source type, published date, detected location, summary, extracted entities, and relevance keywords.
- Duplicate fingerprint so the same story is not reviewed repeatedly.
- Stored status such as `new`, `briefed`, `ignored`, `duplicate`, or `failed`.

### Stage 2: Editorial Agent

The Editorial Agent is the gatekeeper. It reviews researched items before any article is written.

It decides:
- Is this actually local to the Eastern Free State / Bethlehem area?
- Is it newsworthy, or just a press release rehash?
- Has Life@ already covered it?
- Which article category fits?
- What is the recommended local angle?

Output is a content brief, not an article:
- Suggested headline.
- Recommended angle.
- Source URLs to reference.
- Suggested category and tags.
- Confidence score.
- Locality score.
- Novelty or duplicate risk.
- Human-readable reason for approval/rejection.

Human approval is required before writing begins. Initially, the admin panel should show a "Brief Review" queue where an editor can approve, reject, or edit the brief.

### Stage 3: Jimmy, The Reporter/Article Writer Agent

Once a brief is approved, Jimmy:
- Re-reads the source URLs using web fetch/search, not only the stored research summary.
- Writes in the Life@ house style: community-focused, useful, local, and not sensationalist.
- Produces English and Afrikaans versions in one pass where practical.
- Generates SEO title, meta description, excerpt, slug, tags, and push teaser.
- Flags uncertainty, weak sourcing, missing quotes, and claims needing human review.

Prompt requirements:
- Include a Life@ style guide with word-count targets, tone, source citation rules, bilingual guidance, and examples of good Life@ articles.
- Forbid invented quotes, dates, crime details, municipal outcomes, prices, or names.
- Require source URLs and a claim checklist in the output.
- Keep output as a draft article only.

### Stage 4: Image Agent

Image options ranked by practical quality and cost:

| Service | Quality | Estimated Cost | API |
| --- | --- | --- | --- |
| OpenAI image generation | Very good | provider/tier dependent | Yes |
| Google Gemini image generation | Very good | provider/tier dependent | Yes |
| NVIDIA NIM image generation | Very good | provider/model dependent | Yes |
| Flux Pro via fal.ai | Excellent | about USD 0.05 per image | Yes |
| Stable Diffusion self-hosted | Good | server cost only | Yes |
| Ideogram | Good for text in images | free tier / paid plans | Yes |

For news articles, generated images should be illustrative/editorial-style images, not fake documentary photos. Do not generate images that look like real photos of real people or real local events and present them as news photography.

Image rules:
- Label generated images as "AI-generated illustration".
- Preferred testing provider is OpenRouter with a free image-capable model where possible. Preferred direct production providers are OpenAI image generation and Google Gemini image generation, with NVIDIA NIM image endpoints available as a configurable direct-provider option when you want Flux/NIM-style generation.
- NVIDIA image generation should keep its base URL editable because hosted NVIDIA endpoints are model-specific, while self-hosted Visual GenAI NIM deployments can expose `/v1/infer`.
- Prefer real supplied photos, official media, or licensed/stock images when accuracy matters.
- Generated prompt style should make the illustration nature clear, for example: "Editorial illustration of a community meeting in a small South African town, warm afternoon light, clearly illustrative news style."

### Stage 5: Publishing To Life@

The publishing agent submits into Laravel through an internal API or job action. It creates a draft article, attaches the image, sets category/tags, stores source references, and notifies an editor.

It must not auto-publish initially. The article should sit at `draft` or `pending_review` until a human editor reviews and publishes it.

Laravel pipeline:

```text
Laravel Scheduler (daily/hourly)
    -> ResearchJob: fetch RSS, NewsAPI, alerts, and search sources
    -> EditorialAgentJob: score/filter raw items and create ArticleBrief records
    -> Human approves brief in admin panel
    -> JimmyWritingJob: generate bilingual draft article from approved brief
    -> ImageJob: generate or attach illustration/media
    -> Article remains draft/pending_review for editor publication
```

Admin surfaces needed:
- Brief Review queue: approve, reject, merge duplicate, edit angle, assign category.
- Draft Review queue: review, edit, request rewrite, publish.
- Prompt template editor: tune research, editorial, writing, and image prompts without touching code.
- Source monitor: see which feeds are noisy, stale, or producing useful briefs.
- Cost and quality dashboard: articles drafted, articles published, rejection reasons, provider/model used, and average cost.

Estimated cost per article:

| Stage | Tool | Estimated Cost |
| --- | --- | --- |
| Research | NewsAPI free tier / Google RSS | about R0 |
| Editorial brief | Claude Sonnet or similar | about R0.05 |
| Full article write | Claude Sonnet, about 3k tokens | about R0.40 |
| Image | DALL-E / Flux | about R0.75 |
| Total | Per AI-drafted article | about R1.20 |

At roughly R1.20 per article, 100 AI-drafted articles per month is about R120 before provider price changes, retries, and currency movement. Human editors still review and publish; AI handles research grunt work and first drafts.

Recommended build order:
1. Start with the RSS/NewsAPI research collector and store raw items in the database. Validate that the local signal is good before adding AI. Implemented first with `research_sources`, `research_items`, default Google News RSS searches, configured RSS feed support, dedupe, and `life:research:collect`.
2. Add the Editorial Agent to turn raw items into reviewable briefs. Build the admin Brief Review queue. Implemented with `article_briefs`, `life:editorial:brief`, and admin approve/reject/edit review controls.
3. Add Jimmy with web fetch/search for approved briefs. Create article drafts only.
4. Add image generation last. Implemented with `life:images:generate`, OpenRouter/OpenAI/Gemini/NVIDIA NIM image provider settings, article featured-image metadata, public "AI-generated illustration" labelling, and an editor-triggered Image Agent button on article drafts. Testing should use OpenRouter first and select a free image-capable model if one is available.

### Right Model Per Stage

Model choices should be configurable per pipeline stage because cost, quality, and provider capabilities change quickly. Treat these as the recommended starting profile, not permanent hard-coded choices.

Pricing snapshot note:
- Re-check official provider pricing before production rollout and before large scheduled runs.
- Currency estimates assume a rough USD to ZAR conversion and should be recalculated from live exchange rates.
- Google, Anthropic, OpenAI, and fal.ai pricing all change often; store provider/model on every `ai_generation`, `research_item`, `article_brief`, and draft article for later cost/quality comparisons.

Stage 1 and 2: Research Agent + Editorial Agent:
- Recommended primary: Gemini 2.5 Flash for high-volume research scoring, feed summarisation, locality scoring, categorisation, translation, and duplicate-risk checks.
- Cost reason: research and editorial triage feed the model many snippets and ask for structured scoring, so low-cost long-context input matters more than premium prose quality.
- Official pricing snapshot checked on 2026-05-23: Gemini 2.5 Flash listed Standard pricing at about USD 0.30 input / USD 2.50 output per 1M tokens, with Batch/Flex around USD 0.15 input / USD 1.25 output per 1M tokens. Gemini 2.5 Flash-Lite is cheaper and can be tested for low-risk scoring.
- Practical estimate: a normal local research run should still be roughly cents-level, about R0.02 to R0.05 when prompts stay compact and outputs are short.
- Implementation preference: use Gemini for feed triage first; use Gemini grounding/search only when the RSS/search collector did not provide enough source context.

Stage 3: Article Writing:
- Recommended primary: Claude Sonnet 4.6 for full article drafting, bilingual output, careful local context, source-sensitive summaries, and polished community-news tone.
- Quality reason: this is where nuance matters. The writer must handle article structure, English/Afrikaans versions, uncertainty flags, and a Life@ house style without making the copy feel generic.
- Official pricing snapshot checked on 2026-05-23: Anthropic lists Claude Sonnet 4.6 at about USD 3 input / USD 15 output per 1M tokens, with prompt caching offering up to 90% input savings and 1M context available in API beta.
- Practical estimate: a reviewed 800-word article draft should be roughly R0.35 to R0.50 before caching, and closer to about R0.25 when the reusable house-style prompt is cached.
- Implementation preference: keep Jimmy on the provider with the best writing quality, even if research and scoring run on cheaper models.

Stage 4: Image Agent:
- Testing primary: OpenRouter image generation with a free image-capable model where possible.
- Production direct-provider primary: OpenAI image generation or Google Gemini image generation.
- Production/direct-provider alternative: NVIDIA NIM image generation, especially if Life@ later wants a provider-managed or self-hosted Flux-style image path with editable model-specific endpoints.
- Cost reason: image generation is usually priced per image or image-token tier, so model choice is more about quality, style consistency, safety controls, and API reliability than token economics.
- Official pricing snapshot checked on 2026-05-23: OpenAI `gpt-image-1` pricing translates to roughly USD 0.02, USD 0.07, and USD 0.19 for low, medium, and high-quality square images; fal.ai exposes per-model unit pricing through its pricing API, so Flux endpoint pricing should be pulled live when estimating.
- NVIDIA NIM image cost should be configured per selected endpoint/model with `AI_COST_NVIDIA_IMAGE_PER_IMAGE`; keep the planning placeholder in rand until the exact production model is chosen.
- Practical estimate: keep the planning assumption around R0.75 per image for editorial illustrations unless the selected model/tier proves cheaper.

Cost saver: prompt caching:
- Enable prompt caching from day one where providers support it.
- The Life@ house style guide, editorial rules, bilingual rules, local context, source-citation rules, and safety rules repeat on every article draft.
- Cache those repeated prompt sections so each article mostly pays for the unique brief, sources, and generated output.
- Store cache eligibility in prompt metadata so the same reusable blocks are not rewritten differently on every request.

Revised working cost per article with caching:

| Stage | Recommended Model | Estimated Cost |
| --- | --- | --- |
| Research + brief | Gemini 2.5 Flash or Flash-Lite | about R0.04 |
| Jimmy article writing | Claude Sonnet 4.6 with cached house-style prompt | about R0.25 |
| Image | OpenRouter free where possible, then OpenAI / Gemini image generation | about R0 to R0.75 |
| Total | Per AI-drafted article | about R1.04 |

At about R1.04 per AI-drafted article, 100 articles per month is roughly R104 before provider price changes, retries, exchange-rate movement, and manual review time.

Recommendation summary:
- Use Gemini 2.5 Flash or Flash-Lite for research, scoring, categorisation, and low-risk translation.
- Use Claude Sonnet 4.6 for Jimmy's actual article writing and source-sensitive editorial drafting.
- Use OpenRouter/free models while testing; move to OpenAI or Gemini image generation for labelled editorial illustrations when ready for production.
- Enable prompt caching immediately.
- Leave DeepSeek out of the editorial/news pipeline by default because of privacy concerns.

## 3. Business Directory - Reduce Friction And Improve Quality

- Easy: AI listing description generator. Staff or owners enter rough notes such as "hardware store in Reitz, open 15 years, plumbing and roofing supplies"; AI produces a polished bilingual listing description.
- Easy: Listing quality scorer. Rate listings 1-100 for completeness across logo, gallery, hours, phone, address, GPS, category, social links, description, and package status.
- Medium: Duplicate detection. Compare new listing submissions against existing businesses to catch duplicates under slightly different names.
- Medium: Auto-categorisation. Suggest the correct category from the platform taxonomy based on business name, description, and products.
- Medium: Smart "similar businesses" recommendations. Use semantic similarity rather than only same-category filtering.
- Easy: WhatsApp onboarding summariser. Staff paste a WhatsApp conversation with a business owner; AI extracts trading name, phone, services, address clues, hours, and missing fields.
- Easy: Business profile polish. Generate short tagline, long description, service bullets, owner-friendly "about us", and Afrikaans version.
- Medium: Missing-data follow-up message. Generate a friendly WhatsApp/SMS script asking the owner for the exact missing listing fields.
- Medium: Review response helper. Suggest polite owner responses to customer reviews, with escalation language for complaints.
- Complex: Local reputation snapshot. Summarise reviews, listing completeness, voucher usage, campaign activity, and customer interest into a staff-facing client health view.

Implementation notes:
- This is the strongest first admin-side AI feature because staff-assisted onboarding is core to revenue.
- The listing-first package rule must remain intact: AI can improve the listing, but it must not bypass paid listing entitlements.
- Store generated listing copy as draft until accepted.

## 4. Faults And Civic Reporting - High-Impact Civic AI

- Easy: Natural language fault description to auto-category. A user types "water spraying from the pipe outside Spar on Church Street"; AI selects "Burst Pipe / Water Leak" and pre-fills location context.
- Medium: Photo analysis for fault type. Use a vision model to detect potholes, broken streetlights, dumping, water leaks, dangerous wires, missing signage, or road damage.
- Medium: Duplicate and cluster detection. If several reports are nearby and similar, link them as likely duplicates or a cluster.
- Medium: Auto-priority scoring. Score severity using fault type, proximity to schools/clinics, number of reports, danger language, photos, and age.
- Medium: Auto-drafted escalation letter. For unresolved faults after a threshold, generate a formal municipal escalation letter in both languages.
- Easy: Resolution summary for councillors. Weekly digest: faults reported, resolved, overdue, clusters, and hot wards.
- Easy: Resident-friendly status explanation. Convert internal status changes into plain-language updates such as "Reported to municipality", "Escalated", or "Follow-up needed".
- Medium: Councillor action assistant. Suggest the next best action: call municipality, request reference number, ask resident for photo, escalate publicly, or mark duplicate.
- Medium: Evidence pack generator. Create a PDF-ready bundle with report details, map, photos, timeline, duplicate cluster, and previous escalation messages.
- Complex: Civic heatmap narrative. Generate monthly ward-level summaries of recurring infrastructure issues and response-time patterns.

Implementation notes:
- Keep AI suggestions visible to councillors/admin before any official letter is sent.
- Use Google Maps-derived coordinates and reverse geocoding where possible for stronger location quality.
- Fault photo analysis must have a manual override because poor photos and low light can mislead the model.

## 5. Taxi And Delivery - Operational AI

- Medium: Natural language booking. Users type "I need a bakkie to move furniture from Church St to the township on Saturday morning"; AI extracts pickup, dropoff, vehicle type, schedule, load, notes, and payment preference.
- Medium: Smart fare estimator. Use distance, vehicle type, time of day, service mode, passenger/load requirements, current availability, and configured pricing profiles.
- Medium: Demand forecasting. Predict busy slots from historical requests, local events, weather, payday patterns, and school/community calendars.
- Easy: Safety flag detection. If a trip deviates from expected route, stops too long, or loses location unexpectedly, trigger a manager/support alert.
- Easy: Driver message assistant. Generate clear pickup/dropoff instructions and bilingual customer messages from request data.
- Medium: Vehicle fit checker. Match parcel size, passenger count, fragile goods, and heavy-load notes to the right approved vehicle class.
- Medium: Cancellation and dispute summariser. Summarise request timeline, chat notes, GPS events, proof photos, and payment status for manager review.
- Complex: Dispatch optimisation. Recommend which driver should receive which request first based on distance, duty state, vehicle fit, acceptance history, and safety rules.

Implementation notes:
- Build on the existing transport request, duty session, vehicle, fare, Google Maps, and tracking foundations.
- Safety features should be conservative: AI can flag risk, but manager/support should review high-impact actions.
- Keep websocket/realtime workflows authoritative; AI should assist dispatch and review, not replace transactional assignment rules.

## 6. Classifieds - Quality And Trust

- Easy: AI listing description helper. Convert rough text such as "selling old Mahindra bakkie, 2012, needs work" into a complete classified listing.
- Easy: Price suggester. Suggest an asking price range using item category, condition, age, local dataset history, and optional staff-approved reference ranges.
- Medium: Scam and fraud detection. Flag suspicious patterns like too-good pricing, upfront payment requests, mismatched contact details, copied photos, or pressure language.
- Easy: Auto-categorisation. Classify title and description into the correct category.
- Easy: Photo quality helper. Warn when images are blurry, too dark, duplicated, or missing important angles.
- Medium: Seller safety checklist. Generate safe-meetup reminders and detect risky wording before publishing.
- Medium: Duplicate listing detection. Catch repeated classifieds by title, phone number, image similarity, and description.
- Complex: Market trend dashboard. Show which categories are growing, average price movement, and popular searches per town.

Implementation notes:
- Trust features matter more than fancy writing here.
- AI fraud flags should feed the existing moderation queue, not automatically delete listings at first.

## 7. Advertise And Onboarding - Convert More Clients

- Easy: AI package recommender. Business owner answers three questions: business type, budget, goal. AI recommends the right package combination and explains why.
- Easy: AI-generated ad copy. Draft banner text, push copy, and short promotional options from the business listing and offer.
- Easy: Voucher offer generator. Suggest compelling offers such as first-visit discounts, bundled services, loyalty hooks, or seasonal promos.
- Medium: ROI projection tool. Estimate likely reach, clicks, leads, and voucher redemptions from package selection and platform traffic.
- Easy: Staff sales script. Generate a plain-language sales pitch for the exact selected package, including objection handling and next step.
- Easy: Checkout explanation assistant. Explain why listing is required first and what unlocks after purchase.
- Medium: Creative compliance checker. Flag adverts with missing offer details, misleading claims, poor image ratios, or weak call-to-action.
- Medium: Campaign improvement suggestions. After a campaign runs, suggest better wording, targeting, voucher pairing, or push timing.
- Complex: Sales pipeline assistant. Combine listing quality, package status, spend history, renewals, vouchers, campaign performance, and staff notes into next-best action recommendations.

Implementation notes:
- Preserve the listing-first rule as a hard product constraint.
- Vouchers should stay a free acquisition tool for listed businesses, and AI should help generate better voucher ideas rather than turning them into a separate paid product by default.
- Push copy generation should integrate with the existing rich push composer and attachment presets.

## 8. Events - Discovery And Creation

- Easy: Event description writer. Organiser enters rough details; AI creates a publish-ready bilingual description.
- Medium: Personalised event recommendations. Recommend events based on location, viewed content, saved interests, business interactions, and article topics.
- Medium: Duplicate and overlap detection. Flag events at the same venue or with similar names that overlap.
- Easy: Event checklist assistant. Check whether date, time, venue, GPS, ticket info, organiser, thumbnail, and banner are complete.
- Easy: Event-to-push generator. Create push notification options for upcoming events with urgency levels and action buttons.
- Medium: Nearby bundle suggestions. Recommend related businesses, vouchers, and articles to show on the event detail page.
- Complex: Local calendar intelligence. Predict event conflicts, busy weekends, seasonal opportunities, and promotional windows for organisers.

Implementation notes:
- Keep event publication tied to active business entitlement.
- Event recommendations must work without feeling invasive; start with location and declared interests before deeper behavioural targeting.
- Phase 1 implementation now adds an AI Event Description Writer to admin and listing-owner event forms. It drafts title, slug, excerpt, description, venue/city hints, Afrikaans summary, missing fields, and follow-up copy, while saving only after human review.

## 9. Vouchers And Loyalty - Make Promotions Work Harder

- Easy: Voucher copy generator. Turn a plain offer into polished voucher title, description, terms, redemption instructions, and Afrikaans version.
- Easy: Offer strength scorer. Rate whether a voucher is clear, valuable, time-bound, easy to redeem, and likely to drive first visits.
- Medium: Redemption pattern insights. Summarise which offers redeem best by category, location, time of week, and channel.
- Medium: Abuse and duplicate redemption flags. Detect unusual redemption spikes, repeated device/user patterns, or suspicious staff redemption behaviour.
- Medium: Voucher pairing recommendations. Suggest which articles, events, businesses, and push audiences should feature a voucher.
- Complex: Dynamic offer recommendations. Recommend voucher types per business category using platform redemption data over time.

Implementation notes:
- Vouchers are a powerful free acquisition tool for listed businesses.
- AI should help staff and business owners create better offers without weakening redemption controls.

## 10. Platform-Wide AI - Cross-Cutting Features

- Easy: Jimmy chatbot. Persistent chat that answers from live platform content: businesses, articles, events, classifieds, vouchers, faults, and transport help.
- Easy: Spoken Jimmy. Add an optional speaker button that reads Jimmy answers in English or Afrikaans using ElevenLabs by default, while keeping the text answer and source links visible.
- Easy: Bilingual AI throughout. Any generated copy, summaries, notifications, and support messages should be available in English and Afrikaans.
- Medium: Smart push notification targeting. Segment users by location and interests instead of only broad city/region blasts.
- Medium: Content moderation pipeline. Review classifieds, fault reports, comments, business copy, event copy, and public submissions for spam, abuse, and POPIA-risky personal data.
- Medium: Staff CRM assistant. Summarise listing quality, spend, renewal date, campaign history, voucher usage, and recommended follow-up.
- Medium: Weekly platform digest email. Personalised "what is happening near you" email with stories, events, new businesses, vouchers, classifieds, and faults.
- Complex: Admin analytics narrative. Weekly bilingual narrative for admin: growth, revenue, campaigns, listings, faults, transport, content, and risks.
- Easy: Admin "explain this screen" helper. In admin dashboards, summarise what changed since last week and which items need attention.
- Medium: Notification timing optimiser. Recommend best send windows based on past open/click behaviour, town, content type, and urgency.
- Medium: POPIA redaction assistant. Detect ID numbers, private phone numbers, addresses, minors' names, and sensitive personal details before public display.
- Medium: Support reply assistant. Draft helpful responses to users asking about listings, payments, faults, transport, or account issues.
- Medium: Staff training bot. Internal bot trained on Life@ workflows, package rules, moderation policy, and onboarding scripts.
- Complex: Personalised homepage modules. Rearrange home sections for logged-in users based on location, interests, and recent activity.
- Complex: Local knowledge graph. Connect people, businesses, places, events, faults, vouchers, articles, and categories for stronger discovery and analytics.

Implementation notes:
- The chatbot should use retrieval from Life@ content and cite source objects internally.
- It should refuse to invent business hours, prices, municipal statuses, or emergency advice when Life@ does not have the data.
- Start the chatbot with platform content only. Add external web sources later if there is a clear editorial workflow.
- Spoken answers should use ElevenLabs first, cache repeated audio, and require a user click before playback. NVIDIA Speech NIM can be tested from Dev/admin when a hosted or local endpoint is available.

## 11. Admin, Finance, And Operations AI

- Easy: Finance anomaly summary. Flag unusual payment failures, renewal drops, invoice spikes, refunds, and staff payout changes.
- Easy: Invoice and payment explanation helper. Convert invoice/payment status into plain-language support copy.
- Medium: Renewal risk scoring. Identify listings likely to expire without renewal based on engagement, listing completeness, package use, and campaign results.
- Medium: Staff wallet and commission assistant. Summarise commissions earned, pending payouts, disputed entries, and next admin actions.
- Medium: Admin queue prioritiser. Rank listings, adverts, writer applications, classifieds, faults, payouts, and support tickets by urgency.
- Complex: Revenue growth copilot. Suggest which towns, categories, and businesses should be targeted next using gaps in directory coverage and local demand.

Implementation notes:
- These are internal-only launch candidates with low public risk and high staff value.
- They should be surfaced as admin dashboard cards and weekly digest emails before becoming a full copilot.

## 12. Reviews, Reputation, And Community Trust

- Easy: Review summariser. Summarise common praise and complaints on a business listing.
- Easy: Owner response helper. Draft polite, locally appropriate responses to reviews.
- Medium: Review abuse detection. Flag harassment, spam, fake review patterns, personal data, and competitor attacks.
- Medium: Trust badge suggestions. Recommend badges such as "complete profile", "responds to reviews", "active voucher", and "verified location".
- Complex: Reputation trend narrative. Show whether a business is improving or declining over time using reviews, voucher activity, and customer interactions.

Implementation notes:
- Public reputation AI must be careful and conservative.
- Never label fraud publicly from AI alone. Use "needs review" internally.

## Quick-Win Priorities

The highest value, lowest complexity starting points:

1. AI listing description generator.
   - Helps staff-assisted onboarding immediately.
   - Improves public directory quality.
   - Uses data already captured in listings.

2. Listing quality scorer and missing-data follow-up message.
   - Gives sales staff a clear daily worklist.
   - Directly improves conversion and renewal readiness.

3. Fault categorisation from natural language.
   - Reduces friction for low-tech civic reports.
   - Gives the DA/councillor workflow a strong civic-impact story.

4. Article SEO/meta/translation assistant.
   - Helps writers publish faster.
   - Improves bilingual content reach.
   - Keeps editor approval in place.

5. AI-generated ad, push, and voucher copy.
   - Builds on existing packages, push composer, vouchers, and listing-first monetisation.
   - Gives sales staff and business owners immediate value.

6. Jimmy chatbot, limited to platform data.
   - High visibility.
   - Useful even while content volume is still growing.
   - Should launch after basic retrieval and source controls are ready.

## Recommended Build Phases

### Phase 1: Internal AI Tools And Audit Foundation

Goal: create reusable AI infrastructure and launch safe staff/admin tools.

Deliverables:
- AI provider settings in Dev/admin.
- `AiGatewayService`.
- `ai_generations` audit table.
- Prompt templates for listing descriptions, SEO meta, article translation, fault categorisation, push copy, and voucher copy.
- Listing description generator.
- Listing quality scorer.
- Article SEO/meta assistant.
- Fault natural-language categoriser.
- Admin review UI for generated text.

Acceptance checks:
- AI can be disabled globally.
- Provider errors fail gracefully.
- Every generated output is logged.
- Generated public copy requires user/staff acceptance.

### Phase 2: Bilingual Content And Commercial Assistants

Goal: make AI useful across content, listings, adverts, push, vouchers, and events.

Deliverables:
- AI advert, push, and voucher copy assistant for admin/staff and listing-owner workflows. Implemented in the first commercial continuation slice.
- Article English/Afrikaans AI translation from the article editor, saved into `content_translations` with AI generation audit logging. Implemented as an editor-triggered slice; queue/bulk expansion remains next.
- Editorial pipeline foundation: research collector, ArticleBrief review queue, JimmyWritingJob, and image generation as separate stages.
- Event description writer.
- Classified description helper and auto-category.
- Advert/push/voucher copy generator.
- Package recommender.
- Missing-data follow-up scripts.
- Review response helper.

Acceptance checks:
- Editors and business owners can edit before saving.
- Afrikaans translations are labelled and reviewable.
- Listing-first package gating remains enforced.
- Moderation queue receives AI risk flags but does not auto-delete content.

### Phase 3: Semantic Search And Jimmy

Goal: make discovery feel genuinely intelligent.

Deliverables:
- Public Jimmy widget with live Life@ source retrieval, fallback answers, and source links. Implemented in the first continuation slice without embeddings.
- Embeddings pipeline for listings, articles, events, classifieds, vouchers, and fault categories.
- Natural language search intent extraction.
- Similar businesses and related content suggestions.
- Jimmy retrieval chatbot with source-aware answers.
- Query correction and bilingual synonym support.

Acceptance checks:
- Chatbot answers only from indexed Life@ sources unless explicitly configured otherwise.
- Search still works when AI is unavailable.
- User-facing answers link to source pages.
- No sensitive admin-only content is indexed into public retrieval.

### Phase 4: Civic, Safety, And Trust Intelligence

Goal: improve community reliability and operational response.

Deliverables:
- Fault duplicate/cluster detection.
- Fault priority scoring.
- Fault escalation letter generator.
- Fault evidence pack generator.
- Classified scam detection.
- POPIA redaction assistant.
- Transport safety flags and dispute summaries.

Acceptance checks:
- Councillor/admin approval is required before escalation letters are sent.
- Scam/fraud flags route to moderation.
- Safety alerts are conservative and logged.
- AI never silently changes official civic status.

### Phase 5: Personalisation, Forecasting, And Narrative Analytics

Goal: move from assistants to decision intelligence.

Deliverables:
- Personalised weekly digest.
- Smart push targeting and timing optimiser.
- Event recommendations.
- Demand forecasting for transport.
- Admin analytics narrative.
- Revenue growth copilot.
- Local knowledge graph.

Acceptance checks:
- Users can manage communication preferences.
- Personalisation respects privacy and consent.
- Admin narrative includes links back to underlying data.
- Forecasts are labelled as estimates, not facts.

## Suggested Data Model

Start with a simple audit table:

```text
ai_generations
- id
- feature_key
- source_type
- source_id
- user_id
- provider
- model
- prompt_version
- input_hash
- input_summary
- output_language
- output_payload
- status: draft, accepted, edited, rejected, failed
- error_message
- token_input_estimate
- token_output_estimate
- cost_estimate
- reviewed_by
- reviewed_at
- created_at
- updated_at
```

For embeddings:

```text
ai_embeddings
- id
- source_type
- source_id
- visibility: public, authenticated, admin
- locale
- content_hash
- embedding_provider
- embedding_model
- embedding_vector
- indexed_at
- created_at
- updated_at
```

If the database cannot store vectors efficiently at first, start with a queued table and add vector search later through a supported database extension or managed vector store.

For the editorial pipeline:

```text
research_items
- id
- source_name
- source_type: rss, news_api, search_api, alert_email, manual
- source_url
- title
- summary
- raw_payload
- published_at
- fetched_at
- detected_locations
- detected_entities
- fingerprint
- status: new, briefed, ignored, duplicate, failed
- created_at
- updated_at

article_briefs
- id
- research_item_id
- title
- angle
- source_urls
- suggested_category_id
- suggested_tags
- locality_score
- newsworthiness_score
- confidence_score
- duplicate_risk
- editorial_notes
- status: pending_review, approved, rejected, drafted
- reviewed_by
- reviewed_at
- created_at
- updated_at
```

The article draft now reuses the existing `articles` table and `content_translations` flow. Jimmy-created drafts are linked back to the approved `article_brief` through `articles.article_brief_id`, stay at `status = draft`, and carry editor notes with source limitations and verification flags.

## Prompt Safety Rules

Every prompt should include:
- The exact task.
- The allowed source fields.
- The target language.
- The expected JSON shape where structured output is needed.
- A rule to say when information is missing.
- A rule not to invent prices, business hours, municipal outcomes, medical/legal advice, or payment status.
- A rule not to expose private user, payment, or admin data.

For public-facing text:
- Avoid overpromising.
- Avoid fake guarantees.
- Avoid pretending AI output was written by a human.
- Use local, plain language.
- Keep Afrikaans translations natural, not word-for-word stiff.
- Label AI-generated article images as illustrations.
- Do not present generated people, scenes, or events as real news photography.

## First Implementation Slice

The cleanest first build is:

1. Add AI provider settings and a test prompt in Dev/admin.
2. Add the `ai_generations` table and `AiGatewayService`.
3. Add listing description generation in staff/admin listing forms.
4. Add listing quality score to the admin listing index/detail page.
5. Add fault description auto-category in the public fault report flow.
6. Add article SEO/meta generation in the article editor.
7. Add Jimmy from live public platform data.
8. Add commercial copy helpers for adverts, push campaigns, and vouchers.
9. Add AI-assisted article translation and event description drafting.
10. Add the RSS/Google News research collector and scheduled `life:research:collect` command.
11. Add the Editorial Agent brief generator, human review queue, and scheduled `life:editorial:brief` command.
12. Add Jimmy, the reporter/article writer agent, to turn approved briefs into unpublished article drafts with English/Afrikaans content, SEO metadata, tags, and source-review notes.

This first slice creates the core AI plumbing while shipping visible value in directory, civic reporting, and editorial workflows.
