<?php

namespace App\Console\Commands;

use App\Ai\Knowledge\KnowledgeVisibility;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KnowledgeAuditCommand extends Command
{
    protected $signature = 'life:knowledge:audit {--fail : Return a failure code when issues are found}';

    protected $description = 'Audit the public AI index for unsafe, expired, failed, duplicate, and model-mismatched records.';

    public function handle(): int
    {
        $expectedModel = (string) config('ai_platform.embeddings.model');
        $expectedDimensions = (int) config('ai_platform.embeddings.dimensions');
        $checks = [
            'non-public documents' => KnowledgeDocument::query()->where('visibility', '!=', KnowledgeVisibility::PUBLIC)->count(),
            'expired documents still indexed' => KnowledgeDocument::query()->where('expires_at', '<=', now())->count(),
            'documents without chunks' => KnowledgeDocument::query()->doesntHave('chunks')->count(),
            'chunks without embeddings' => KnowledgeChunk::query()->whereNull('embedding')->count(),
            'model-mismatched chunks' => KnowledgeChunk::query()->where(function ($query) use ($expectedModel, $expectedDimensions): void {
                $query->where('embedding_model', '!=', $expectedModel)
                    ->orWhere('embedding_dimensions', '!=', $expectedDimensions);
            })->count(),
            'duplicate source documents' => DB::table('knowledge_documents')
                ->select('source_type', 'source_id', 'locale')->groupBy('source_type', 'source_id', 'locale')->havingRaw('COUNT(*) > 1')->count(),
        ];

        $this->table(['Check', 'Count'], collect($checks)->map(fn (int $count, string $name): array => [$name, $count])->values()->all());
        $issues = array_sum($checks);
        $this->{$issues === 0 ? 'info' : 'warn'}($issues === 0 ? 'Knowledge index audit passed.' : "Knowledge index audit found {$issues} issues.");

        return $issues > 0 && $this->option('fail') ? self::FAILURE : self::SUCCESS;
    }
}
