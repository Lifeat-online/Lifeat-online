<?php

namespace App\Ai\PublicAssistant;

use Illuminate\Support\Collection;

class RetrievalRanker
{
    public function rank(Collection $sources, array $search, array $intent): Collection
    {
        $baseTerms = $search['base_terms'] ?? [];
        $expandedTerms = $search['terms'] ?? [];
        $sourceTypes = array_values($intent['source_types'] ?? []);

        return $sources->map(function (array $source) use ($baseTerms, $expandedTerms, $sourceTypes, $search): array {
            $haystack = mb_strtolower(($source['title'] ?? '').' '.($source['summary'] ?? '').' '.($source['location'] ?? '').' '.json_encode($source['meta'] ?? []));
            $score = 0;
            foreach ($baseTerms as $term) {
                $score += $term !== '' && str_contains($haystack, mb_strtolower($term)) ? 8 : 0;
            }
            foreach ($expandedTerms as $term) {
                $score += $term !== '' && str_contains($haystack, mb_strtolower($term)) ? 3 : 0;
            }
            $typeIndex = array_search($source['type'] ?? '', $sourceTypes, true);
            if ($typeIndex !== false) {
                $score += max(4, 18 - ($typeIndex * 2));
            }
            if (filled($search['location'] ?? null) && str_contains($haystack, mb_strtolower((string) $search['location']))) {
                $score += 10;
            }
            if (($source['type'] ?? '') === 'business' && (bool) data_get($source, 'meta.featured')) {
                $score += 2;
            }
            $source['relevance_score'] = $score;

            return $source;
        })->sortByDesc('relevance_score')->values();
    }
}
