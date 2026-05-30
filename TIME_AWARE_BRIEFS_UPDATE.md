# Time-Aware Editorial Briefs Update

## Overview
The editorial brief pipeline is now time-aware in PHP, not only in the AI prompt. Research items must have a source `published_at` date inside the configured fresh-news window before they can become briefs, be approved, or be drafted by Jimmy.

## Changes Made

### 1. Freshness Policy (`app/Support/Editorial/BriefFreshness.php`)

- New shared freshness helper calculates source age, freshness label, deterministic timeliness score, and approval eligibility.
- Default approval window: `LIFE_RESEARCH_BRIEF_MAX_AGE_DAYS=7`.
- Undated, future-suspicious, or older-than-window reports are not approvable.
- Newsworthiness is capped by source age, so an old story cannot keep a high score from the model.

### 2. AI Prompt Updates (`app/Support/Ai/AiPromptCatalog.php`)

#### System Prompt Enhancement
- **Version bumped** from `editorial_brief_v1` to `editorial_brief_v3`
- Added explicit **temporal awareness** guidelines:
  - Always consider current date when evaluating newsworthiness
  - Approve/review only stories published within the last 7 days
  - Reject older, undated, or historical recap stories for this workflow
  - Explicitly note dated content in editorial notes
  - Never call a story recent unless `published_at` is inside the freshness policy

#### New Schema Field
- Added `timeliness_score`: Number from 0-100 indicating content freshness
  - 100 = breaking news
  - 85 = 1 to 3 days
  - 65 = 4 to 7 days
  - 0 = old or undated
- Updated `newsworthiness_score` description to emphasize temporal relevance weighting
- Updated `editorial_notes` to include temporal concerns

### 3. Collection and Service Layer

- `ResearchCollectorService` skips stale feed items before writing `research_items`.
- `EditorialBriefService` marks stale/undated research items as ignored before calling the AI.
- AI context includes current date/time and a `freshness_policy` block.
- Brief creation stores deterministic `timeliness_score`, capped `newsworthiness_score`, and a freshness note.

### 4. Database (`database/migrations/`)

Created migration: `2026_05_28_100000_add_timeliness_score_to_article_briefs_table.php`
- Adds `timeliness_score` decimal column (5,2) to `article_briefs` table
- Positioned after `newsworthiness_score`
- Default value: 0

### 5. Model (`app/Models/ArticleBrief.php`)

Updated to include `timeliness_score` and freshness helpers:
- `freshness()`
- `effectiveTimelinessScore()`
- `freshnessAdjustedNewsworthinessScore()`
- `isFreshEnoughForApproval()`

### 6. Controller and Jimmy

- Brief edits reapply freshness caps before saving.
- Approval is blocked server-side when the source is too old or undated.
- Jimmy refuses to draft stale approved briefs.

### 7. View (`resources/views/admin/article-briefs/index.blade.php`)

Updated UI to display source age visibly:
- Added `Fresh`, `Age`, and `Published` boxes in the preview grid
- Added `Source published`, `Source age`, and `Freshness rule` boxes in the edit form
- Stale briefs show a red warning and the approval button is disabled

## How It Works

1. **Collection Gate**: Old feed items are skipped before becoming research items.
2. **Brief Gate**: Stale/undated research items are ignored before any AI call.
3. **Score Enforcement**: Timeliness and newsworthiness are recalculated/capped in PHP.
4. **Approval Gate**: Stale briefs cannot be approved from the admin UI or by direct POST.
5. **Draft Gate**: Jimmy will not draft a stale brief even if it was approved earlier.

## Benefits

- **No More Old Briefs**: Year-old or undated stories cannot pass through the brief pipeline
- **Breaking News Priority**: Fresh stories automatically score higher
- **Human Awareness**: Editors see clear freshness, age, published-date boxes, and temporal warnings
- **Better Content Quality**: Platform focuses on current, relevant local news

## Migration Required

Run the migration to add the new database column:
```bash
php artisan migrate
```

## Backward Compatibility

- Existing briefs without `timeliness_score` will default to 0
- All new briefs will have this field enforced by PHP
- Existing stale briefs remain visible for rejection but cannot be approved
