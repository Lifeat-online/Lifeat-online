<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\EmbeddingProvider;

class FakeEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(private readonly int $vectorDimensions = 1536)
    {
    }

    public function embed(array $texts): array
    {
        return array_map(fn (string $text): array => $this->vector($text), array_values($texts));
    }

    public function dimensions(): int
    {
        return $this->vectorDimensions;
    }

    public function model(): string
    {
        return 'fake-deterministic';
    }

    /** @return list<float> */
    private function vector(string $text): array
    {
        $bytes = hash('sha512', $text, true);
        $length = strlen($bytes);
        $vector = [];

        for ($index = 0; $index < $this->vectorDimensions; $index++) {
            $vector[] = (ord($bytes[$index % $length]) - 127.5) / 127.5;
        }

        return $vector;
    }
}
