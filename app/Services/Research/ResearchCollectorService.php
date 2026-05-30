<?php

namespace App\Services\Research;

use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Support\Editorial\BriefFreshness;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class ResearchCollectorService
{
    public function seedDefaultSources(): array
    {
        $created = 0;
        $updated = 0;

        foreach ((array) config('life_research.default_sources', []) as $sourceConfig) {
            $slug = (string) ($sourceConfig['slug'] ?? '');

            if ($slug === '') {
                continue;
            }

            $source = ResearchSource::query()->firstOrNew(['slug' => $slug]);
            $exists = $source->exists;

            $source->fill([
                'name' => (string) ($sourceConfig['name'] ?? Str::headline($slug)),
                'type' => (string) ($sourceConfig['type'] ?? ResearchSource::TYPE_GOOGLE_NEWS_RSS),
                'url' => $sourceConfig['url'] ?? null,
                'query' => $sourceConfig['query'] ?? null,
                'locale' => (string) ($sourceConfig['locale'] ?? config('life_research.default_locale', 'en-ZA')),
                'country' => (string) ($sourceConfig['country'] ?? config('life_research.default_country', 'ZA')),
                'fetch_interval_minutes' => (int) ($sourceConfig['fetch_interval_minutes'] ?? 60),
                'metadata' => (array) ($sourceConfig['metadata'] ?? []),
            ]);

            if (! $exists) {
                $source->is_active = (bool) ($sourceConfig['is_active'] ?? true);
                $created++;
            } else {
                $updated++;
            }

            $source->save();
        }

        return ['created' => $created, 'updated' => $updated];
    }

    public function collect(array $sourceSlugs = [], ?int $limit = null, bool $dryRun = false): array
    {
        $limit ??= (int) config('life_research.default_limit', 25);
        $limit = max(1, min(100, $limit));

        $sources = ResearchSource::query()
            ->active()
            ->when($sourceSlugs !== [], fn ($query) => $query->whereIn('slug', $sourceSlugs))
            ->orderBy('name')
            ->get();

        $summary = [
            'sources' => $sources->count(),
            'parsed' => 0,
            'created' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'failed' => 0,
            'dry_run' => $dryRun,
            'source_results' => [],
        ];

        foreach ($sources as $source) {
            $result = $this->collectSource($source, $limit, $dryRun);

            foreach (['parsed', 'created', 'duplicates', 'skipped', 'failed'] as $key) {
                $summary[$key] += $result[$key] ?? 0;
            }

            $summary['source_results'][] = $result;
        }

        return $summary;
    }

    public function collectSource(ResearchSource $source, int $limit = 25, bool $dryRun = false): array
    {
        $result = [
            'source' => $source->slug,
            'parsed' => 0,
            'created' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'failed' => 0,
            'error' => null,
        ];

        try {
            $url = $this->feedUrl($source);
            $response = $this->fetch($url);
            $items = array_slice($this->parseFeed($response->body(), $source), 0, max(1, $limit));
            $result['parsed'] = count($items);

            foreach ($items as $item) {
                if (($item['title'] ?? '') === '') {
                    $result['skipped']++;
                    continue;
                }

                if (! BriefFreshness::assess($item['published_at'] ?? null)['approvable']) {
                    $result['skipped']++;
                    continue;
                }

                $fingerprint = $this->fingerprint($item);
                $existing = ResearchItem::query()->where('fingerprint', $fingerprint)->first();

                if ($existing) {
                    $result['duplicates']++;
                    continue;
                }

                if (! $dryRun) {
                    ResearchItem::create([
                        'research_source_id' => $source->id,
                        'source_name' => $item['source_name'] ?? $source->name,
                        'source_type' => $source->type,
                        'source_url' => $item['url'] ?? null,
                        'external_id' => $item['external_id'] ?? null,
                        'title' => $item['title'],
                        'summary' => $item['summary'] ?? null,
                        'author' => $item['author'] ?? null,
                        'raw_payload' => $item['raw_payload'] ?? [],
                        'published_at' => $item['published_at'] ?? null,
                        'fetched_at' => now(),
                        'detected_locations' => $this->detectLocations($item, $source),
                        'detected_entities' => [],
                        'fingerprint' => $fingerprint,
                        'status' => ResearchItem::STATUS_NEW,
                    ]);
                }

                $result['created']++;
            }

            $source->update([
                'last_fetched_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $result['failed']++;
            $result['error'] = $exception->getMessage();
            $source->update(['last_error' => Str::limit($exception->getMessage(), 1000, '')]);
        }

        return $result;
    }

    private function feedUrl(ResearchSource $source): string
    {
        if ($source->type === ResearchSource::TYPE_RSS) {
            if (! $source->url) {
                throw new RuntimeException("Research source [{$source->slug}] has no RSS URL.");
            }

            return $source->url;
        }

        if ($source->type === ResearchSource::TYPE_GOOGLE_NEWS_RSS) {
            $locale = $source->locale ?: (string) config('life_research.default_locale', 'en-ZA');
            $country = $source->country ?: (string) config('life_research.default_country', 'ZA');
            $language = Str::of($locale)->before('-')->lower()->value() ?: 'en';

            return 'https://news.google.com/rss/search?'.http_build_query([
                'q' => $source->query ?: $source->name,
                'hl' => $locale,
                'gl' => $country,
                'ceid' => $country.':'.$language,
            ], '', '&', PHP_QUERY_RFC3986);
        }

        throw new RuntimeException("Unsupported research source type [{$source->type}].");
    }

    private function fetch(string $url): Response
    {
        $response = Http::withHeaders([
            'User-Agent' => (string) config('life_research.user_agent'),
        ])
            ->accept('application/rss+xml, application/xml, text/xml, */*')
            ->timeout((int) config('life_research.timeout', 20))
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Feed returned HTTP '.$response->status().'.');
        }

        return $response;
    }

    private function parseFeed(string $body, ResearchSource $source): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);

        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Feed response was not valid XML.');
        }

        if (isset($xml->channel->item)) {
            return $this->parseRssItems($xml->channel->item, $source);
        }

        if (isset($xml->entry)) {
            return $this->parseAtomItems($xml->entry, $source);
        }

        $namespaces = $xml->getNamespaces(true);
        if (isset($namespaces[''])) {
            $children = $xml->children($namespaces['']);
            if (isset($children->entry)) {
                return $this->parseAtomItems($children->entry, $source);
            }
        }

        return [];
    }

    private function parseRssItems(iterable $items, ResearchSource $source): array
    {
        $parsed = [];

        foreach ($items as $item) {
            $title = $this->cleanText((string) $item->title);
            $summary = $this->cleanSummary((string) ($item->description ?? ''));
            $sourceName = $this->cleanText((string) ($item->source ?? $source->name));

            $parsed[] = [
                'title' => $title,
                'summary' => $summary,
                'url' => trim((string) $item->link),
                'external_id' => trim((string) ($item->guid ?? '')),
                'author' => $this->cleanText((string) ($item->author ?? '')),
                'source_name' => $sourceName ?: $source->name,
                'published_at' => $this->parseDate((string) ($item->pubDate ?? '')),
                'raw_payload' => [
                    'title' => $title,
                    'description' => $summary,
                    'link' => trim((string) $item->link),
                    'guid' => trim((string) ($item->guid ?? '')),
                    'pubDate' => trim((string) ($item->pubDate ?? '')),
                    'source' => $sourceName,
                ],
            ];
        }

        return $parsed;
    }

    private function parseAtomItems(iterable $items, ResearchSource $source): array
    {
        $parsed = [];

        foreach ($items as $item) {
            $title = $this->cleanText((string) $item->title);
            $summary = $this->cleanSummary((string) ($item->summary ?? $item->content ?? ''));
            $link = trim((string) $item->link);

            foreach ($item->link as $linkNode) {
                $attributes = $linkNode->attributes();
                if (isset($attributes['href'])) {
                    $link = trim((string) $attributes['href']);
                    break;
                }
            }

            $parsed[] = [
                'title' => $title,
                'summary' => $summary,
                'url' => $link,
                'external_id' => trim((string) ($item->id ?? '')),
                'author' => $this->cleanText((string) ($item->author->name ?? '')),
                'source_name' => $source->name,
                'published_at' => $this->parseDate((string) ($item->published ?? $item->updated ?? '')),
                'raw_payload' => [
                    'title' => $title,
                    'summary' => $summary,
                    'link' => $link,
                    'id' => trim((string) ($item->id ?? '')),
                    'published' => trim((string) ($item->published ?? '')),
                    'updated' => trim((string) ($item->updated ?? '')),
                ],
            ];
        }

        return $parsed;
    }

    private function cleanText(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function cleanSummary(string $value): string
    {
        return Str::limit(preg_replace('/\s+/', ' ', $this->cleanText($value)) ?: '', 2000, '');
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function fingerprint(array $item): string
    {
        $url = $this->normalizedUrl((string) ($item['url'] ?? ''));

        if ($url !== '') {
            return hash('sha256', $url);
        }

        return hash('sha256', Str::lower(($item['title'] ?? '').'|'.($item['published_at'] ?? '')));
    }

    private function normalizedUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host'])) {
            return Str::lower($url);
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);

        $query = collect($query)
            ->reject(fn ($value, string $key): bool => Str::startsWith($key, 'utm_') || in_array($key, ['fbclid', 'gclid'], true))
            ->all();

        $normalized = strtolower((string) ($parts['scheme'] ?? 'https')).'://'.strtolower((string) $parts['host']);
        $normalized .= $parts['path'] ?? '';

        if ($query !== []) {
            $normalized .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return rtrim($normalized, '/');
    }

    private function detectLocations(array $item, ResearchSource $source): array
    {
        $metadata = (array) ($source->metadata ?? []);
        $keywords = array_values(array_unique(array_filter([
            ...((array) config('life_research.location_keywords', [])),
            ...((array) ($metadata['locations'] ?? [])),
        ])));

        $haystack = Str::lower(($item['title'] ?? '').' '.($item['summary'] ?? ''));

        return collect($keywords)
            ->filter(fn (string $keyword): bool => $keyword !== '' && Str::contains($haystack, Str::lower($keyword)))
            ->values()
            ->all();
    }
}
