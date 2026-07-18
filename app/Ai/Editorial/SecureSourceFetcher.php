<?php

namespace App\Ai\Editorial;

use App\Ai\Editorial\Contracts\HostResolver;
use App\Models\ResearchItem;
use App\Models\SourceSnapshot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SecureSourceFetcher
{
    public function __construct(private readonly HostResolver $resolver)
    {
    }

    public function snapshot(ResearchItem $item): SourceSnapshot
    {
        $url = (string) $item->source_url;
        $allowedHosts = $this->allowedHosts($item);

        for ($redirect = 0; $redirect <= 3; $redirect++) {
            [$host, $addresses] = $this->safeEndpoint($url, $allowedHosts);
            $response = Http::accept('text/html, text/plain, application/json, application/xml')
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => $this->curlResolveEntries($host, $addresses)],
                ])
                ->timeout(12)->connectTimeout(5)->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');
                if (! $location || $redirect === 3) {
                    throw new \RuntimeException('Source redirect limit exceeded.');
                }
                $url = $this->absoluteUrl($url, $location);
                continue;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            if (! Str::contains($contentType, ['text/', 'application/json', 'application/xml', 'application/rss+xml', 'application/atom+xml'])) {
                throw new \RuntimeException('Source content type is not allowlisted: '.$contentType);
            }
            if (strlen($response->body()) > 2_000_000) {
                throw new \RuntimeException('Source response exceeds the 2 MB limit.');
            }

            $content = $this->readableText($response->body());

            return SourceSnapshot::create([
                'research_item_id' => $item->id,
                'url' => $url,
                'http_status' => $response->status(),
                'content_type' => $contentType,
                'content' => $content,
                'content_hash' => hash('sha256', $content),
                'response_headers' => collect($response->headers())->map(fn ($values) => is_array($values) ? implode(', ', $values) : $values)->all(),
                'fetch_error' => $response->successful() ? null : 'HTTP '.$response->status(),
                'fetched_at' => now(),
            ]);
        }

        throw new \RuntimeException('Source could not be fetched.');
    }

    /** @return array{string, list<string>} */
    private function safeEndpoint(string $url, array $allowedHosts): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? null) !== 'https' || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException('Source URL must be an authenticated-free HTTPS URL.');
        }
        if (! in_array($host, $allowedHosts, true)) {
            throw new \RuntimeException('Source host is not allowlisted: '.$host);
        }

        $addresses = $this->resolver->addresses($host);
        if ($addresses === []) {
            throw new \RuntimeException('Source host did not resolve.');
        }
        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException('Source host resolves to a private or reserved address.');
            }
        }

        return [$host, $addresses];
    }

    /** @param list<string> $addresses @return list<string> */
    private function curlResolveEntries(string $host, array $addresses): array
    {
        return array_map(
            fn (string $address): string => $host.':443:'.(str_contains($address, ':') ? '['.$address.']' : $address),
            $addresses
        );
    }

    /** @return list<string> */
    private function allowedHosts(ResearchItem $item): array
    {
        $configured = (array) data_get($item->researchSource?->metadata, 'allowed_hosts', []);
        $registryHost = parse_url((string) $item->researchSource?->url, PHP_URL_HOST);

        return collect($configured)->push($registryHost)->filter()->map(fn ($host) => strtolower((string) $host))->unique()->values()->all();
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (filter_var($location, FILTER_VALIDATE_URL)) {
            return $location;
        }

        $parts = parse_url($base);
        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').'/'.ltrim($location, '/');
    }

    private function readableText(string $body): string
    {
        $body = preg_replace('/<(script|style|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $body) ?? $body;
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 100_000, '');
    }
}
