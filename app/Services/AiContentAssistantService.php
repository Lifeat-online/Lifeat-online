<?php

namespace App\Services;

use App\Models\Article;
use App\Models\AiGeneration;
use App\Models\CivicFaultReport;
use App\Models\Event;
use App\Models\Listing;
use App\Models\User;
use App\Models\Voucher;
use App\Support\Ai\AiPromptCatalog;
use Illuminate\Support\Str;

class AiContentAssistantService
{
    public function __construct(
        private readonly AiGatewayService $gateway,
        private readonly AiPromptCatalog $prompts,
    ) {}

    public function listingQualityScore(Listing $listing): array
    {
        $listing->loadMissing(['categories', 'activeSubscription']);

        $checks = [
            'Trading name' => ['weight' => 5, 'done' => filled($listing->title)],
            'Category' => ['weight' => 12, 'done' => $listing->categories->isNotEmpty()],
            'Rich description' => ['weight' => 18, 'done' => mb_strlen(strip_tags((string) $listing->description)) >= 160],
            'Short excerpt' => ['weight' => 7, 'done' => filled($listing->excerpt)],
            'Phone number' => ['weight' => 8, 'done' => filled($listing->phone)],
            'Email or website' => ['weight' => 7, 'done' => filled($listing->email) || filled($listing->website_url)],
            'Address and town' => ['weight' => 10, 'done' => filled($listing->address_line) && filled($listing->city)],
            'GPS coordinates' => ['weight' => 8, 'done' => filled($listing->latitude) && filled($listing->longitude)],
            'Logo' => ['weight' => 8, 'done' => filled($listing->logo_path)],
            'Image or gallery' => ['weight' => 7, 'done' => filled($listing->featured_image) || $this->hasPhotos($listing)],
            'Published status' => ['weight' => 5, 'done' => $listing->status === 'published'],
            'Active package' => ['weight' => 5, 'done' => $listing->hasActiveBusinessEntitlement()],
        ];

        $score = collect($checks)
            ->sum(fn (array $check): int => $check['done'] ? $check['weight'] : 0);

        $missing = collect($checks)
            ->filter(fn (array $check): bool => ! $check['done'])
            ->keys()
            ->values()
            ->all();

        return [
            'score' => $score,
            'label' => $this->qualityLabel($score),
            'missing' => $missing,
            'completed' => collect($checks)->filter(fn (array $check): bool => $check['done'])->keys()->values()->all(),
        ];
    }

    public function generateListingDescription(array $input, ?Listing $listing, ?User $user): array
    {
        $prompt = $this->prompts->get('listing_description');

        return $this->gateway->generateStructured(
            'listing_description',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'business directory listing copy',
                    'schema' => $prompt['schema'],
                    'rules' => [
                        'Do not invent missing facts.',
                        'Use the supplied categories as context, not as guaranteed services.',
                        'Keep copy appropriate for a local Eastern Free State audience.',
                    ],
                ],
                'listing' => $this->cleanInput($input),
            ],
            $listing,
            $user,
            $prompt['output_language'],
        );
    }

    public function generateArticleSeo(array $input, ?User $user): array
    {
        $prompt = $this->prompts->get('article_seo');

        return $this->gateway->generateStructured(
            'article_seo',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'article SEO metadata',
                    'schema' => $prompt['schema'],
                    'rules' => [
                        'Base every suggestion only on the supplied article fields.',
                        'Keep metadata accurate for search engines and social previews.',
                    ],
                ],
                'article' => $this->cleanInput($input),
            ],
            null,
            $user,
            'en',
        );
    }

    public function translateArticle(Article $article, string $targetLocale, ?User $user, bool $force = false): array
    {
        $sourceLocale = $article->sourceLocale();

        if ($targetLocale === $sourceLocale) {
            return ['ok' => false, 'message' => 'Target language must be different from the article source language.'];
        }

        $source = $article->translatableContent();
        if ($source === []) {
            return ['ok' => false, 'message' => 'No article content is available to translate.'];
        }

        $sourceHash = $article->contentSourceHash();
        $existing = $article->contentTranslations()->where('locale', $targetLocale)->first();

        if (! $force && $existing && $existing->source_hash === $sourceHash) {
            return [
                'ok' => true,
                'message' => 'AI translation is already current.',
                'payload' => $existing->content,
                'translation' => $existing,
            ];
        }

        $prompt = $this->prompts->get('article_translation');
        $result = $this->gateway->generateStructured(
            'article_translation',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'article field translation',
                    'schema' => $prompt['schema'],
                    'source_locale' => $sourceLocale,
                    'target_locale' => $targetLocale,
                    'rules' => [
                        'Keep all keys exactly the same.',
                        'Translate naturally for Life@ readers.',
                        'Preserve names, places, URLs, phone numbers, currency, paragraph breaks, and HTML or markdown.',
                    ],
                ],
                'source_fields' => $source,
            ],
            $article,
            $user,
            $targetLocale,
        );

        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        $translated = $this->normalizedTranslatedFields((array) ($result['payload'] ?? []), $source);
        if ($translated === []) {
            return [
                'ok' => false,
                'message' => 'AI provider returned no translated article fields.',
                'generation' => $result['generation'] ?? null,
            ];
        }

        $translation = $article->contentTranslations()->updateOrCreate(
            ['locale' => $targetLocale],
            [
                'content' => $translated,
                'source_locale' => $sourceLocale,
                'source_hash' => $sourceHash,
                'provider' => $this->gateway->provider(),
                'model' => $this->gateway->model(),
                'translated_at' => now(),
            ]
        );

        if (($result['generation'] ?? null) instanceof AiGeneration) {
            $result['generation']->update([
                'status' => AiGeneration::STATUS_ACCEPTED,
                'reviewed_by' => $user?->id,
                'reviewed_at' => now(),
            ]);
        }

        return [
            'ok' => true,
            'message' => 'AI-assisted translation saved.',
            'payload' => $translated,
            'translation' => $translation,
            'generation' => ($result['generation'] ?? null)?->fresh(),
        ];
    }

    public function generateEventDescription(array $input, ?Listing $listing, ?Event $event, ?User $user): array
    {
        $prompt = $this->prompts->get('event_description');

        return $this->gateway->generateStructured(
            'event_description',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'business-linked community event listing',
                    'schema' => $prompt['schema'],
                    'rules' => [
                        'Use the supplied listing only as context for the organiser or venue.',
                        'Do not invent dates, prices, performers, speakers, booking links, sponsors, or availability.',
                        'If key event details are missing, list them in missing_fields and ask for them in follow_up_message.',
                        'Keep the tone useful for local residents, not like a national marketing campaign.',
                    ],
                ],
                'event' => $this->cleanInput($input),
                'listing' => $this->listingContext($listing),
                'current_event' => $this->eventContext($event),
            ],
            $event ?: $listing,
            $user,
            $prompt['output_language'],
        );
    }

    public function generateAdCopy(array $input, ?Listing $listing, ?Event $event, ?User $user): array
    {
        $prompt = $this->prompts->get('ad_copy');

        return $this->gateway->generateStructured(
            'ad_copy',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'listing-linked advert campaign',
                    'schema' => $prompt['schema'],
                    'rules' => [
                        'Use supplied listing and event facts as context only.',
                        'If the offer is vague, ask for missing details instead of inventing them.',
                        'Keep copy practical for Eastern Free State residents.',
                    ],
                ],
                'campaign' => $this->cleanInput($input),
                'listing' => $this->listingContext($listing),
                'event' => $this->eventContext($event),
            ],
            $listing,
            $user,
            'en',
        );
    }

    public function generatePushCopy(array $input, ?Listing $listing, ?Event $event, ?User $user): array
    {
        $prompt = $this->prompts->get('push_copy');

        return $this->gateway->generateStructured(
            'push_copy',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'business-linked push notification campaign',
                    'schema' => $prompt['schema'],
                    'rules' => [
                        'Keep headline and message short enough for push notifications.',
                        'Do not imply the user subscribed to a business if the audience is broader.',
                        'Use a clear local call to action.',
                    ],
                ],
                'campaign' => $this->cleanInput($input),
                'listing' => $this->listingContext($listing),
                'event' => $this->eventContext($event),
            ],
            $listing,
            $user,
            'en',
        );
    }

    public function generateVoucherCopy(array $input, ?Listing $listing, ?Voucher $voucher, ?User $user): array
    {
        $prompt = $this->prompts->get('voucher_copy');

        return $this->gateway->generateStructured(
            'voucher_copy',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'target' => 'business voucher offer',
                    'schema' => $prompt['schema'],
                    'rules' => [
                        'Never invent discount values, expiry dates, usage limits, or redemption terms.',
                        'Make the offer clear enough for a customer and staff member to understand.',
                        'Keep the voucher suitable for a listed local business.',
                    ],
                ],
                'voucher' => $this->cleanInput($input),
                'listing' => $this->listingContext($listing),
            ],
            $voucher ?: $listing,
            $user,
            'en',
        );
    }

    public function suggestFaultCategory(string $description, ?string $addressLabel, ?User $user): array
    {
        $fallback = $this->fallbackFaultSuggestion($description);

        if (! $this->gateway->configured()) {
            return [
                'ok' => true,
                'source' => 'fallback',
                'message' => 'Suggested from local keywords because no AI provider is configured.',
                'payload' => $fallback,
            ];
        }

        $prompt = $this->prompts->get('fault_category');

        $result = $this->gateway->generateStructured(
            'fault_category',
            $prompt['version'],
            $prompt['system'],
            [
                'instructions' => [
                    'schema' => $prompt['schema'],
                    'allowed_categories' => CivicFaultReport::categories(),
                    'allowed_severities' => CivicFaultReport::severities(),
                ],
                'report' => [
                    'description' => Str::limit($description, 500, ''),
                    'address_label' => $addressLabel,
                ],
            ],
            null,
            $user,
            'en',
        );

        if (! ($result['ok'] ?? false)) {
            return [
                'ok' => true,
                'source' => 'fallback',
                'message' => ($result['message'] ?? 'AI unavailable').'. Used local keyword suggestion.',
                'payload' => $fallback,
            ];
        }

        $payload = (array) ($result['payload'] ?? []);
        $payload['category'] = array_key_exists((string) ($payload['category'] ?? ''), CivicFaultReport::categories())
            ? (string) $payload['category']
            : $fallback['category'];
        $payload['severity'] = array_key_exists((string) ($payload['severity'] ?? ''), CivicFaultReport::severities())
            ? (string) $payload['severity']
            : $fallback['severity'];
        $payload['confidence'] = max(0, min(1, (float) ($payload['confidence'] ?? 0.6)));
        $payload['explanation'] = (string) ($payload['explanation'] ?? $fallback['explanation']);
        $payload['location_hint'] = (string) ($payload['location_hint'] ?? '');

        return [
            'ok' => true,
            'source' => 'ai',
            'message' => $result['message'] ?? 'AI suggestion generated.',
            'payload' => $payload,
            'generation_id' => ($result['generation'] ?? null)?->id,
        ];
    }

    private function hasPhotos(Listing $listing): bool
    {
        if ($listing->relationLoaded('photos')) {
            return $listing->photos->isNotEmpty();
        }

        return $listing->exists && $listing->photos()->exists();
    }

    private function qualityLabel(int $score): string
    {
        return match (true) {
            $score >= 85 => 'Excellent',
            $score >= 70 => 'Good',
            $score >= 50 => 'Needs work',
            default => 'Incomplete',
        };
    }

    private function cleanInput(array $input): array
    {
        return collect($input)
            ->map(function ($value) {
                if (is_array($value)) {
                    return $this->cleanInput($value);
                }

                if (! is_scalar($value) && $value !== null) {
                    return null;
                }

                $value = is_string($value) ? trim($value) : $value;

                return is_string($value) ? Str::limit($value, 5000, '') : $value;
            })
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();
    }

    private function normalizedTranslatedFields(array $payload, array $source): array
    {
        $fields = data_get($payload, 'translated_fields');
        $fields = is_array($fields) ? $fields : $payload;

        return collect($source)
            ->mapWithKeys(function (string $sourceValue, string $key) use ($fields): array {
                $translated = $fields[$key] ?? null;

                return [$key => is_string($translated) && trim($translated) !== '' ? trim($translated) : $sourceValue];
            })
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->all();
    }

    private function listingContext(?Listing $listing): array
    {
        if (! $listing) {
            return [];
        }

        $listing->loadMissing('categories');

        return $this->cleanInput([
            'title' => $listing->title,
            'excerpt' => $listing->excerpt,
            'description' => $listing->description,
            'city' => $listing->city,
            'region' => $listing->region,
            'phone' => $listing->phone,
            'website_url' => $listing->website_url,
            'categories' => $listing->categories->pluck('name')->values()->all(),
        ]);
    }

    private function eventContext(?Event $event): array
    {
        if (! $event) {
            return [];
        }

        return $this->cleanInput([
            'title' => $event->title,
            'excerpt' => $event->excerpt,
            'description' => $event->description,
            'venue_name' => $event->venue_name,
            'city' => $event->city,
            'region' => $event->region,
            'start_at' => $event->start_at?->toDateTimeString(),
            'end_at' => $event->end_at?->toDateTimeString(),
        ]);
    }

    private function fallbackFaultSuggestion(string $description): array
    {
        $text = Str::lower($description);
        $category = 'other';
        $severity = CivicFaultReport::SEVERITY_MEDIUM;

        $matches = [
            'burst_pipe' => ['water', 'pipe', 'leak', 'spray', 'burst', 'flood'],
            'pothole' => ['pothole', 'hole', 'road', 'tar', 'street damage'],
            'streetlight' => ['streetlight', 'light', 'lamp', 'dark'],
            'sanitation' => ['dump', 'rubbish', 'trash', 'sewage', 'sanitation', 'drain'],
            'electricity' => ['electric', 'wire', 'cable', 'power', 'spark'],
            'sidewalk' => ['sidewalk', 'pavement', 'kerb'],
        ];

        foreach ($matches as $candidate => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    $category = $candidate;
                    break 2;
                }
            }
        }

        if (str_contains($text, 'danger') || str_contains($text, 'sparking') || str_contains($text, 'open wire')) {
            $severity = CivicFaultReport::SEVERITY_URGENT;
        } elseif (str_contains($text, 'large') || str_contains($text, 'flood') || str_contains($text, 'blocked')) {
            $severity = CivicFaultReport::SEVERITY_HIGH;
        } elseif (str_contains($text, 'small') || str_contains($text, 'minor')) {
            $severity = CivicFaultReport::SEVERITY_LOW;
        }

        return [
            'category' => $category,
            'severity' => $severity,
            'confidence' => 0.45,
            'explanation' => 'Suggested from keywords in the report description.',
            'location_hint' => '',
        ];
    }
}
