<?php

namespace App\Ai\Operator\Contracts;

interface WebSearchProvider
{
    /** @return list<array{title:string,url:string,snippet:string,published_at:?string,source:?string}> */
    public function search(string $query, string $locale = 'en-ZA', int $limit = 8): array;
}
