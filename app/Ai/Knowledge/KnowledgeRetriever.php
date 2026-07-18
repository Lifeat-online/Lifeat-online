<?php

namespace App\Ai\Knowledge;

use App\Ai\Contracts\EmbeddingProvider;
use App\Models\KnowledgeChunk;
use App\Models\AiRetrievalTrace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KnowledgeRetriever
{
    public function __construct(private readonly EmbeddingProvider $embeddings)
    {
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, string $locale = 'en', int $limit = 5): array
    {
        $startedAt = hrtime(true);
        $limit = min(20, max(1, $limit));
        $vector = $this->embeddings->embed([$query])[0] ?? [];

        [$lexical, $semantic] = DB::getDriverName() === 'pgsql'
            ? $this->postgresCandidates($query, $vector, $locale, max(20, $limit * 4))
            : $this->portableCandidates($query, $vector, $locale);

        $scores = [];
        foreach ([$lexical, $semantic] as $ranking) {
            foreach (array_values($ranking) as $rank => $candidate) {
                $id = $candidate['id'];
                $scores[$id] = ($scores[$id] ?? 0.0) + (1 / (60 + $rank + 1));
            }
        }

        arsort($scores);
        $chunks = $this->publicQuery($locale)
            ->whereIn('knowledge_chunks.id', array_keys($scores))
            ->with('document')
            ->get()
            ->keyBy('id');

        $results = collect($scores)->take($limit)->map(function (float $score, int|string $id) use ($chunks): ?array {
            $chunk = $chunks->get((int) $id);
            if (! $chunk) {
                return null;
            }

            return [
                'chunk_id' => $chunk->id,
                'document_id' => $chunk->knowledge_document_id,
                'source_type' => $chunk->document->source_type,
                'source_id' => $chunk->document->source_id,
                'title' => $chunk->document->title,
                'url' => $chunk->document->canonical_url,
                'content' => $chunk->content,
                'score' => $score,
            ];
        })->filter()->values()->all();

        AiRetrievalTrace::create([
            'trace_id' => (string) str()->uuid(),
            'query_hash' => hash('sha256', mb_strtolower(trim($query))),
            'locale' => $locale,
            'lexical_candidates' => count($lexical),
            'semantic_candidates' => count($semantic),
            'selected_sources' => collect($results)->map(fn (array $result): array => [
                'source_type' => $result['source_type'],
                'source_id' => $result['source_id'],
                'score' => $result['score'],
            ])->values()->all(),
            'embedding_model' => $this->embeddings->model(),
            'embedding_dimensions' => $this->embeddings->dimensions(),
            'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ]);

        return $results;
    }

    /** @return array{0: list<array{id:int, score:float}>, 1: list<array{id:int, score:float}>} */
    private function portableCandidates(string $query, array $vector, string $locale): array
    {
        $terms = $this->terms($query);
        $chunks = $this->publicQuery($locale)->with('document')->limit(1000)->get();

        $lexical = $chunks->map(function (KnowledgeChunk $chunk) use ($terms): array {
            $haystack = mb_strtolower($chunk->document->title.' '.$chunk->content);
            $matches = collect($terms)->filter(fn (string $term): bool => str_contains($haystack, $term))->count();

            return ['id' => $chunk->id, 'score' => $terms === [] ? 0.0 : $matches / count($terms)];
        })->filter(fn (array $candidate): bool => $candidate['score'] > 0)->sortByDesc('score')->values()->all();

        $semantic = $chunks->filter(fn (KnowledgeChunk $chunk): bool => $chunk->embedding_model === $this->embeddings->model()
                && (int) $chunk->embedding_dimensions === $this->embeddings->dimensions())
            ->map(fn (KnowledgeChunk $chunk): array => [
                'id' => $chunk->id,
                'score' => $this->cosine($vector, $chunk->embedding ?? []),
            ])->filter(fn (array $candidate): bool => $candidate['score'] > 0)
            ->sortByDesc('score')->values()->all();

        return [$lexical, $semantic];
    }

    /** @return array{0: list<array{id:int, score:float}>, 1: list<array{id:int, score:float}>} */
    private function postgresCandidates(string $query, array $vector, string $locale, int $candidateLimit): array
    {
        $baseBindings = [KnowledgeVisibility::PUBLIC, $locale];
        $visibility = "d.visibility = ? AND d.locale = ? AND d.published_at IS NOT NULL AND d.published_at <= CURRENT_TIMESTAMP AND (d.expires_at IS NULL OR d.expires_at > CURRENT_TIMESTAMP)";

        $lexical = DB::select(
            "SELECT c.id, ts_rank_cd(c.search_vector, websearch_to_tsquery('simple', ?)) AS score
             FROM knowledge_chunks c JOIN knowledge_documents d ON d.id = c.knowledge_document_id
             WHERE {$visibility} AND c.search_vector @@ websearch_to_tsquery('simple', ?)
             ORDER BY score DESC LIMIT {$candidateLimit}",
            [$query, ...$baseBindings, $query]
        );

        $semantic = [];
        if ($vector !== []) {
            $literal = '['.implode(',', array_map(fn ($value): string => (string) (float) $value, $vector)).']';
            $semantic = DB::select(
                "SELECT c.id, 1 - (c.embedding_vector <=> ?::vector) AS score
                 FROM knowledge_chunks c JOIN knowledge_documents d ON d.id = c.knowledge_document_id
                 WHERE {$visibility} AND c.embedding_vector IS NOT NULL
                   AND c.embedding_model = ? AND c.embedding_dimensions = ?
                 ORDER BY c.embedding_vector <=> ?::vector LIMIT {$candidateLimit}",
                [$literal, ...$baseBindings, $this->embeddings->model(), $this->embeddings->dimensions(), $literal]
            );
        }

        $normalize = fn (array $rows): array => array_map(
            fn ($row): array => ['id' => (int) $row->id, 'score' => (float) $row->score],
            $rows
        );

        return [$normalize($lexical), $normalize($semantic)];
    }

    private function publicQuery(string $locale): Builder
    {
        return KnowledgeChunk::query()
            ->select('knowledge_chunks.*')
            ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.knowledge_document_id')
            ->where('knowledge_documents.visibility', KnowledgeVisibility::PUBLIC)
            ->where('knowledge_documents.locale', $locale)
            ->whereNotNull('knowledge_documents.published_at')
            ->where('knowledge_documents.published_at', '<=', now())
            ->where(function (Builder $query): void {
                $query->whereNull('knowledge_documents.expires_at')
                    ->orWhere('knowledge_documents.expires_at', '>', now());
            });
    }

    /** @return list<string> */
    private function terms(string $query): array
    {
        preg_match_all('/[\pL\pN]{3,}/u', mb_strtolower($query), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function cosine(array $left, array $right): float
    {
        if ($left === [] || count($left) !== count($right)) {
            return 0.0;
        }

        $dot = $leftNorm = $rightNorm = 0.0;
        foreach ($left as $index => $value) {
            $dot += $value * $right[$index];
            $leftNorm += $value ** 2;
            $rightNorm += $right[$index] ** 2;
        }

        return ($leftNorm > 0 && $rightNorm > 0) ? $dot / (sqrt($leftNorm) * sqrt($rightNorm)) : 0.0;
    }
}
