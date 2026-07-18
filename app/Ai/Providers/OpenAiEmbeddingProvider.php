<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\EmbeddingProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class OpenAiEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $modelName = 'text-embedding-3-small',
        private readonly int $vectorDimensions = 1536,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {
    }

    public function embed(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        if (trim($this->apiKey) === '') {
            throw new RuntimeException('The OpenAI embedding API key is not configured.');
        }

        foreach ($texts as $text) {
            if (! is_string($text) || trim($text) === '') {
                throw new InvalidArgumentException('Embedding inputs must be non-empty strings.');
            }
        }

        $response = $this->request()
            ->post(rtrim($this->baseUrl, '/').'/embeddings', [
                'model' => $this->modelName,
                'input' => array_values($texts),
                'dimensions' => $this->vectorDimensions,
                'encoding_format' => 'float',
            ])
            ->throw();

        $vectors = collect($response->json('data', []))
            ->sortBy('index')
            ->pluck('embedding')
            ->values()
            ->all();

        if (count($vectors) !== count($texts)) {
            throw new RuntimeException('The embedding provider returned an unexpected number of vectors.');
        }

        return $vectors;
    }

    public function dimensions(): int
    {
        return $this->vectorDimensions;
    }

    public function model(): string
    {
        return $this->modelName;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken($this->apiKey)
            ->timeout(30)
            ->retry(2, 250, throw: false);
    }
}
