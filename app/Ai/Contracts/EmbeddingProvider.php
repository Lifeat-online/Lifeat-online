<?php

namespace App\Ai\Contracts;

interface EmbeddingProvider
{
    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array;

    public function dimensions(): int;

    public function model(): string;
}
