<?php

namespace App\Ai\Knowledge;

class KnowledgeChunker
{
    /** @return list<string> */
    public function chunk(string $content): array
    {
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? '');
        if ($content === '') {
            return [];
        }

        $limit = max(200, (int) config('ai_platform.knowledge.chunk_characters', 1800));
        $overlap = min(max(0, (int) config('ai_platform.knowledge.chunk_overlap_characters', 200)), $limit - 1);
        $chunks = [];
        $offset = 0;
        $length = mb_strlen($content);

        while ($offset < $length) {
            $candidate = mb_substr($content, $offset, $limit);
            if ($offset + $limit < $length) {
                $lastSpace = mb_strrpos($candidate, ' ');
                if ($lastSpace !== false && $lastSpace > (int) ($limit * 0.6)) {
                    $candidate = mb_substr($candidate, 0, $lastSpace);
                }
            }

            $candidate = trim($candidate);
            if ($candidate !== '') {
                $chunks[] = $candidate;
            }

            $consumed = max(1, mb_strlen($candidate));
            $offset += max(1, $consumed - $overlap);
        }

        return $chunks;
    }
}
