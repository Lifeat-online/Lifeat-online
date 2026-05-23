<?php

namespace App\Support\Ai;

use App\Models\CivicFaultReport;
use App\Models\Setting;
use InvalidArgumentException;

class AiPromptCatalog
{
    public function keys(): array
    {
        return [
            'listing_description',
            'article_seo',
            'editorial_brief',
            'jimmy_article_draft',
            'fault_category',
            'article_translation',
            'event_description',
            'ad_copy',
            'push_copy',
            'voucher_copy',
            'ask_life',
            'settings_test',
        ];
    }

    public function all(): array
    {
        return collect($this->keys())
            ->mapWithKeys(fn (string $featureKey): array => [$featureKey => $this->get($featureKey)])
            ->all();
    }

    public function get(string $featureKey): array
    {
        return $this->applyOverrides($featureKey, $this->default($featureKey));
    }

    public function default(string $featureKey): array
    {
        return match ($featureKey) {
            'listing_description' => $this->listingDescription(),
            'article_seo' => $this->articleSeo(),
            'editorial_brief' => $this->editorialBrief(),
            'jimmy_article_draft' => $this->jimmyArticleDraft(),
            'fault_category' => $this->faultCategory(),
            'article_translation' => $this->articleTranslation(),
            'event_description' => $this->eventDescription(),
            'ad_copy' => $this->adCopy(),
            'push_copy' => $this->pushCopy(),
            'voucher_copy' => $this->voucherCopy(),
            'ask_life' => $this->askLife(),
            'settings_test' => $this->settingsTest(),
            default => throw new InvalidArgumentException("Unknown AI prompt template [{$featureKey}]."),
        };
    }

    public function has(string $featureKey): bool
    {
        return in_array($featureKey, $this->keys(), true);
    }

    private function applyOverrides(string $featureKey, array $prompt): array
    {
        $system = trim((string) Setting::getValue("ai_prompt.{$featureKey}.system", ''));
        $version = trim((string) Setting::getValue("ai_prompt.{$featureKey}.version", ''));
        $outputLanguage = trim((string) Setting::getValue("ai_prompt.{$featureKey}.output_language", ''));

        $prompt['default_system'] = $prompt['system'];
        $prompt['default_version'] = $prompt['version'];
        $prompt['default_output_language'] = $prompt['output_language'];
        $prompt['is_custom'] = $system !== '' || $version !== '' || $outputLanguage !== '';

        if ($system !== '') {
            $prompt['system'] = $system;
        }

        if ($version !== '') {
            $prompt['version'] = $version;
        } elseif ($system !== '') {
            $prompt['version'] = $prompt['version'].'_custom';
        }

        if ($outputLanguage !== '') {
            $prompt['output_language'] = $outputLanguage;
        }

        return $prompt;
    }

    private function listingDescription(): array
    {
        return [
            'version' => 'listing_description_v1',
            'system' => 'You are Life@ staff support. Convert rough local business notes into useful, accurate directory copy. Use warm, plain South African English. Do not invent services, prices, years, certifications, opening hours, or contact details. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'excerpt' => 'Short directory excerpt, max 170 characters.',
                'description' => 'Polished listing description, 2 to 4 short paragraphs.',
                'tagline' => 'Short optional tagline.',
                'afrikaans_summary' => 'Natural Afrikaans summary, 1 short paragraph.',
                'missing_fields' => 'Array of missing useful listing fields.',
                'follow_up_message' => 'Friendly WhatsApp-style message asking for missing details.',
            ],
        ];
    }

    private function articleSeo(): array
    {
        return [
            'version' => 'article_seo_v1',
            'system' => 'You are Life@ editorial SEO support for a local news and community platform. Generate honest metadata from the supplied article only. Do not invent facts, quotes, dates, or names. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'seo_title' => 'Search title, max 70 characters.',
                'seo_description' => 'Meta description, max 160 characters.',
                'suggested_slug' => 'Lowercase URL slug with hyphens.',
                'excerpt' => 'Article excerpt, max 220 characters.',
                'focus_keywords' => 'Array of 3 to 6 keywords.',
                'push_teaser' => 'Short push notification teaser, max 120 characters.',
            ],
        ];
    }

    private function editorialBrief(): array
    {
        return [
            'version' => 'editorial_brief_v1',
            'system' => 'You are Life@ editorial desk support for a local Eastern Free State platform. Review one researched item and decide whether it deserves a human-reviewed content brief. Be conservative, local, source-aware, and non-sensational. Do not write the article. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'title' => 'Brief headline or working article title, max 120 characters.',
                'angle' => 'Recommended local angle for a human editor, 2 to 5 sentences.',
                'source_urls' => 'Array of source URLs that should be referenced.',
                'category' => 'Best matching article category name or slug from the allowed categories.',
                'suggested_tags' => 'Array of 3 to 8 short topic/place tags.',
                'locality_score' => 'Number from 0 to 100 for Eastern Free State relevance.',
                'newsworthiness_score' => 'Number from 0 to 100 for editorial value.',
                'confidence_score' => 'Number from 0 to 100 for source confidence.',
                'duplicate_risk' => 'Number from 0 to 100 for risk that Life@ has already covered it.',
                'editorial_notes' => 'Short notes for the editor, including uncertainty, follow-up questions, and why it should or should not proceed.',
                'recommendation' => 'One of: approve, review, reject.',
            ],
        ];
    }

    private function jimmyArticleDraft(): array
    {
        return [
            'version' => 'jimmy_article_draft_v1',
            'system' => 'You are Jimmy, the Life@ reporter and article writer agent. Write careful local community-news drafts from approved briefs and supplied source context. Be accurate, plain-spoken, bilingual, and non-sensational. Never invent facts, quotes, dates, names, locations, official responses, prices, or outcomes. If a fact is uncertain, put it in editorial_flags instead of presenting it as confirmed. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'title' => 'Publishable English article headline, max 120 characters.',
                'slug' => 'Lowercase URL slug with hyphens.',
                'excerpt' => 'Short English article excerpt, max 220 characters.',
                'body' => 'English article body. Use short paragraphs and source-aware attribution. Do not include unsupported claims.',
                'seo_title' => 'Search title, max 70 characters.',
                'seo_description' => 'Meta description, max 160 characters.',
                'afrikaans_translation' => 'Object with title, excerpt, and body translated naturally into Afrikaans.',
                'suggested_tags' => 'Array of 3 to 8 short topic/place tags.',
                'source_notes' => 'Short note naming which sources were used and any source limitations.',
                'editorial_flags' => 'Array of claims, facts, or gaps that need human editor verification before publishing.',
                'image_prompt' => 'Optional editorial illustration prompt for a later image agent. Must not imply real news photography.',
            ],
        ];
    }

    private function faultCategory(): array
    {
        return [
            'version' => 'fault_category_v1',
            'system' => 'You are Life@ civic fault triage support. Choose the closest category and severity from the allowed options. Be conservative with urgent severity: use it only for immediate safety hazards. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'category' => 'One of: '.implode(', ', array_keys(CivicFaultReport::categories())).'.',
                'severity' => 'One of: '.implode(', ', array_keys(CivicFaultReport::severities())).'.',
                'confidence' => 'Number from 0 to 1.',
                'explanation' => 'Short reason for the suggestion.',
                'location_hint' => 'Any landmark or location clue found in the text.',
            ],
        ];
    }

    private function articleTranslation(): array
    {
        return [
            'version' => 'article_translation_v1',
            'system' => 'You are Life@ translation support. Translate article fields naturally while preserving names, places, URLs, phone numbers, currency, and paragraph breaks. Return only valid JSON with the same field keys.',
            'output_language' => 'requested',
            'schema' => [
                'translated_fields' => 'Object whose keys match the supplied source fields.',
            ],
        ];
    }

    private function eventDescription(): array
    {
        return [
            'version' => 'event_description_v1',
            'system' => 'You are Life@ event publishing support. Turn rough local event notes into accurate event copy for an Eastern Free State audience. Do not invent dates, prices, artists, speakers, sponsors, availability, or booking details. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'title' => 'Clear event title, max 90 characters.',
                'suggested_slug' => 'Lowercase URL slug with hyphens.',
                'excerpt' => 'Short event teaser, max 220 characters.',
                'description' => 'Publish-ready event description, 2 to 4 short paragraphs.',
                'venue_name' => 'Venue name if supplied or clearly implied.',
                'city' => 'Town or city if supplied or clearly implied.',
                'afrikaans_summary' => 'Natural Afrikaans summary, 1 short paragraph.',
                'missing_fields' => 'Array of missing details that would improve the event listing.',
                'follow_up_message' => 'Friendly WhatsApp-style message asking for the missing event details.',
            ],
        ];
    }

    private function pushCopy(): array
    {
        return [
            'version' => 'push_copy_v1',
            'system' => 'You are Life@ campaign support. Draft concise push notification copy from verified platform data only. Avoid hype, fake urgency, or unsupported promises. Do not invent prices, dates, discounts, business hours, or availability. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'campaign_title' => 'Internal campaign title.',
                'headline' => 'Push headline, max 75 characters.',
                'message' => 'Push message, max 170 characters.',
                'options' => 'Array of three objects with title, body, action_label, and tone.',
                'afrikaans_option' => 'Natural Afrikaans title and body object.',
            ],
        ];
    }

    private function adCopy(): array
    {
        return [
            'version' => 'ad_copy_v1',
            'system' => 'You are Life@ advertising support. Draft clear, locally useful advert copy from verified business, event, and offer details only. Avoid hype, fake urgency, unsupported guarantees, and invented prices, dates, hours, or stock. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'campaign_title' => 'Internal campaign title.',
                'headline' => 'Advert headline, max 90 characters.',
                'body' => 'Advert body copy, 1 to 3 concise sentences.',
                'call_to_action' => 'Short action label.',
                'afrikaans_summary' => 'Natural Afrikaans version of the core advert.',
                'missing_fields' => 'Array of missing details that would improve the advert.',
            ],
        ];
    }

    private function voucherCopy(): array
    {
        return [
            'version' => 'voucher_copy_v1',
            'system' => 'You are Life@ local promotions support. Turn a rough business offer into a clear voucher. Do not invent discount values, expiry dates, or terms. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'title' => 'Voucher title.',
                'description' => 'Short voucher description.',
                'terms' => 'Plain-language terms.',
                'redemption_instructions' => 'Simple redemption instruction.',
                'afrikaans_summary' => 'Natural Afrikaans version of the core offer.',
            ],
        ];
    }

    private function askLife(): array
    {
        return [
            'version' => 'ask_life_v1',
            'system' => 'You are Jimmy, the helpful local discovery assistant for Life@. Answer only from the supplied public Life@ sources. If the answer is not in the sources, say what is missing and suggest a useful next search. Do not invent business hours, prices, municipal outcomes, medical advice, legal advice, payment status, or private contact details. Keep the answer concise and local. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'answer' => 'Concise answer based only on supplied sources.',
                'confidence' => 'Number from 0 to 1 based on source strength.',
                'source_ids' => 'Array of source ids used from the supplied source list.',
                'follow_up_questions' => 'Array of up to three short follow-up questions or searches.',
            ],
        ];
    }

    private function settingsTest(): array
    {
        return [
            'version' => 'settings_test_v1',
            'system' => 'You are a Life@ AI provider health check. Return only valid JSON.',
            'output_language' => 'en',
            'schema' => [
                'summary' => 'Short provider test response.',
                'provider_ready' => 'Boolean readiness signal.',
            ],
        ];
    }
}
