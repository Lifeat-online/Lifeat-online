<?php

namespace App\Ai\Operator;

use App\Ai\Operator\Contracts\WebSearchProvider;
use App\Services\AiGatewayService;
use Illuminate\Support\Str;

class PerplexityWebSearchProvider implements WebSearchProvider
{
    public function __construct(private readonly AiGatewayService $gateway) {}

    public function search(string $query, string $locale = 'en-ZA', int $limit = 8): array
    {
        $limit = max(1, min(10, $limit));
        $response = $this->gateway->generateStructured(
            'web_search',
            'v1',
            'Search the current public web. Return JSON with a results array. Each result needs title, canonical HTTPS url, concise factual snippet, published_at when known, and source. Prefer official or primary sources and reputable reporting. Never invent URLs or facts.',
            ['query' => $query, 'locale' => $locale, 'limit' => $limit],
        );
        if (! ($response['ok'] ?? false)) {
            throw new \RuntimeException((string) ($response['message'] ?? 'Web search failed.'));
        }

        return collect(data_get($response, 'payload.results', []))
            ->filter(fn ($item): bool => is_array($item) && $this->isPublicHttpsUrl((string) ($item['url'] ?? '')))
            ->map(fn (array $item): array => [
                'title' => Str::limit(trim((string) ($item['title'] ?? 'Untitled source')), 255, ''),
                'url' => trim((string) $item['url']),
                'snippet' => Str::limit(trim((string) ($item['snippet'] ?? '')), 1200, ''),
                'published_at' => filled($item['published_at'] ?? null) ? (string) $item['published_at'] : null,
                'source' => filled($item['source'] ?? null) ? Str::limit((string) $item['source'], 255, '') : null,
            ])
            ->unique('url')
            ->take($limit)
            ->values()
            ->all();
    }

    private function isPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? null) === 'https'
            && filled($parts['host'] ?? null)
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
