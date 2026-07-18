<?php

namespace App\Ai\Evaluation;

use App\Ai\Contracts\EmbeddingProvider;
use App\Ai\Knowledge\KnowledgeRetriever;
use App\Ai\Knowledge\KnowledgeVisibility;
use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\DB;

class MeasuredEvaluation
{
    private const SOURCE_PREFIX = 'evaluation:';

    public function __construct(
        private readonly EvaluationSuite $suite,
        private readonly EmbeddingProvider $embeddings,
        private readonly KnowledgeRetriever $retriever,
    ) {}

    public function run(): array
    {
        $cases = $this->suite->askLifeCases()
            ->filter(fn (array $case): bool => ($case['accepted'] ?? false) && is_string($case['expected_source_type'] ?? null))
            ->values();

        $this->clearDocuments();
        try {
            foreach ($cases as $case) {
                $this->seedCase($case);
            }

            $hits = $validCitations = $selected = $unsafe = 0;
            foreach ($cases as $case) {
                $results = $this->retriever->search($case['question'], $case['locale'], 5);
                $expected = self::SOURCE_PREFIX.$case['id'];
                $hits += collect($results)->contains(fn (array $result): bool => $result['source_id'] === $expected) ? 1 : 0;
                foreach ($results as $result) {
                    $selected++;
                    $validCitations += filled($result['source_id']) && filled($result['url']) ? 1 : 0;
                    $haystack = mb_strtolower($result['content']);
                    $unsafe += collect($case['must_not_include'] ?? [])->contains(fn (string $term): bool => str_contains($haystack, mb_strtolower($term))) ? 1 : 0;
                }
            }

            $total = $cases->count();
            $metrics = [
                'database' => DB::getDriverName(),
                'embedding_model' => $this->embeddings->model(),
                'retrieval_cases' => $total,
                'recall_at_5' => $total ? round($hits / $total, 4) : 0.0,
                'citation_validity' => $selected ? round($validCitations / $selected, 4) : 0.0,
                'unsafe_disclosures' => $unsafe,
                'locale_coverage' => $cases->pluck('locale')->unique()->sort()->values()->all(),
            ];
            $metrics['passed'] = $metrics['recall_at_5'] >= 0.95
                && $metrics['citation_validity'] === 1.0
                && $metrics['unsafe_disclosures'] === 0
                && $metrics['locale_coverage'] === ['af', 'en'];

            return $metrics;
        } finally {
            $this->clearDocuments();
        }
    }

    private function seedCase(array $case): void
    {
        $content = $case['question'].' Verified public evaluation record '.$case['id'].'.';
        $document = KnowledgeDocument::create([
            'source_type' => $case['expected_source_type'],
            'source_id' => self::SOURCE_PREFIX.$case['id'],
            'locale' => $case['locale'],
            'title' => 'Evaluation '.$case['id'],
            'canonical_url' => '/evaluation/'.$case['id'],
            'content' => $content,
            'metadata' => ['evaluation_fixture' => true],
            'content_hash' => hash('sha256', $content),
            'visibility' => KnowledgeVisibility::PUBLIC,
            'published_at' => now()->subMinute(),
            'indexed_at' => now(),
        ]);
        $vector = $this->embeddings->embed([$case['question']])[0];
        $chunk = $document->chunks()->create([
            'position' => 0,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'token_count' => str_word_count($content),
            'character_count' => mb_strlen($content),
            'embedding' => $vector,
            'embedding_provider' => 'evaluation',
            'embedding_model' => $this->embeddings->model(),
            'embedding_dimensions' => $this->embeddings->dimensions(),
            'embedded_at' => now(),
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::update('UPDATE knowledge_chunks SET embedding_vector = ?::vector WHERE id = ?', [
                '['.implode(',', array_map(fn ($value): string => (string) (float) $value, $vector)).']',
                $chunk->id,
            ]);
        }
    }

    private function clearDocuments(): void
    {
        KnowledgeDocument::query()->where('source_id', 'like', self::SOURCE_PREFIX.'%')->delete();
    }
}
