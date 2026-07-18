<?php

namespace App\Services\Research;

use App\Ai\Editorial\SecureSourceFetcher;
use App\Models\ResearchItem;
use App\Models\ResearchSource;
use App\Models\SourceSnapshot;
use Illuminate\Support\Str;

class OnDemandResearchService
{
    public function __construct(private readonly SecureSourceFetcher $fetcher) {}

    public function snapshot(array $source): SourceSnapshot
    {
        $url = trim((string) $source['url']);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! str_starts_with($url, 'https://') || $host === '') {
            throw new \InvalidArgumentException('On-demand research requires a public HTTPS URL.');
        }

        $researchSource = ResearchSource::query()->firstOrCreate(
            ['slug' => 'web-'.substr(hash('sha256', $host), 0, 20)],
            [
                'name' => (string) ($source['source_name'] ?: $host),
                'type' => ResearchSource::TYPE_WEB_SEARCH,
                'url' => 'https://'.$host,
                'locale' => (string) ($source['locale'] ?? 'en-ZA'),
                'is_active' => false,
                'metadata' => [
                    'allowed_hosts' => array_values(array_unique([$host, str_starts_with($host, 'www.') ? substr($host, 4) : 'www.'.$host])),
                    'trust_score' => 70,
                    'task_scoped' => true,
                ],
            ],
        );
        $fingerprint = hash('sha256', Str::lower($url));
        $item = ResearchItem::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'research_source_id' => $researchSource->id,
                'source_name' => (string) ($source['source_name'] ?: $host),
                'source_type' => ResearchSource::TYPE_WEB_SEARCH,
                'source_url' => $url,
                'title' => Str::limit((string) $source['title'], 255, ''),
                'summary' => $source['snippet'] ?: null,
                'published_at' => $source['published_at'] ?? null,
                'fetched_at' => now(),
                'raw_payload' => ['search_result' => $source],
                'status' => ResearchItem::STATUS_NEW,
            ],
        );

        return $item->snapshots()->where('url', $url)->latest('fetched_at')->first()
            ?? $this->fetcher->snapshot($item);
    }
}
