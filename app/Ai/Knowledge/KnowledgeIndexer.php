<?php

namespace App\Ai\Knowledge;

use App\Ai\Contracts\EmbeddingProvider;
use App\Models\Article;
use App\Models\CivicFaultReport;
use App\Models\Classified;
use App\Models\Event;
use App\Models\KnowledgeDocument;
use App\Models\Listing;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KnowledgeIndexer
{
    public function __construct(
        private readonly EmbeddingProvider $embeddings,
        private readonly KnowledgeChunker $chunker,
    ) {
    }

    public function index(Model $record): ?KnowledgeDocument
    {
        return match (true) {
            $record instanceof Article => $this->indexArticle($record),
            $record instanceof Listing => $this->indexListing($record),
            $record instanceof Event => $this->indexEvent($record),
            $record instanceof Voucher => $this->indexVoucher($record),
            $record instanceof Classified => $this->indexClassified($record),
            $record instanceof CivicFaultReport => $this->indexFault($record),
            default => throw new \InvalidArgumentException('Unsupported public knowledge record: '.$record::class),
        };
    }

    public function indexArticle(Article $article): ?KnowledgeDocument
    {
        return $this->store('article', $article, $article->source_locale ?: 'en',
            $article->status === 'published' && $article->published_at !== null,
            $article->title, route('articles.show', $article),
            [$article->title, $article->excerpt, $article->body],
            ['slug' => $article->slug], $article->published_at);
    }

    public function indexListing(Listing $listing): ?KnowledgeDocument
    {
        return $this->store('listing', $listing, 'en', $listing->isPubliclyVisible(),
            $listing->title, route('directory.show', $listing),
            [$listing->title, $listing->excerpt, $listing->description, $listing->city, $listing->region, $listing->country],
            ['slug' => $listing->slug, 'city' => $listing->city, 'region' => $listing->region], $listing->published_at,
            $listing->package_expires_at);
    }

    public function indexEvent(Event $event): ?KnowledgeDocument
    {
        return $this->store('event', $event, 'en', $event->isPubliclyVisible() && ($event->end_at === null || $event->end_at->isFuture()),
            $event->title, route('events.show', $event),
            [$event->title, $event->excerpt, $event->description, $event->venue_name, $event->address_line, $event->city, $event->region, $event->start_at?->toIso8601String()],
            ['slug' => $event->slug, 'city' => $event->city, 'starts_at' => $event->start_at?->toIso8601String()], $event->published_at,
            $event->end_at);
    }

    public function indexVoucher(Voucher $voucher): ?KnowledgeDocument
    {
        $listingPublic = $voucher->listing?->isPubliclyVisible() ?? false;

        return $this->store('voucher', $voucher, 'en', $voucher->isCurrentlyActive() && $listingPublic,
            $voucher->title, $voucher->listing ? route('vouchers.show', [$voucher->listing, $voucher]) : null,
            [$voucher->title, $voucher->description, $voucher->terms, $voucher->formattedValue(), $voucher->listing?->title],
            ['slug' => $voucher->slug, 'value' => $voucher->formattedValue(), 'business' => $voucher->listing?->title], $voucher->published_at,
            $voucher->end_at);
    }

    public function indexClassified(Classified $classified): ?KnowledgeDocument
    {
        return $this->store('classified', $classified, 'en',
            $classified->status === Classified::STATUS_PUBLISHED && $classified->published_at !== null,
            $classified->title, route('classifieds.show', $classified),
            [$classified->title, $classified->description, $classified->city, $classified->region, $classified->country],
            ['slug' => $classified->slug, 'city' => $classified->city, 'price' => $classified->price], $classified->published_at);
    }

    public function indexFault(CivicFaultReport $fault): ?KnowledgeDocument
    {
        $title = (CivicFaultReport::categories()[$fault->category] ?? str($fault->category)->headline()).' near '.$fault->address_label;

        return $this->store('fault', $fault, 'en', (bool) $fault->is_approved,
            $title, route('faults.index', ['category' => $fault->category, 'status' => $fault->status]),
            [$title, $fault->description, $fault->status, $fault->severity],
            ['category' => $fault->category, 'status' => $fault->status, 'severity' => $fault->severity], $fault->created_at);
    }

    public function removeArticle(Article $article): void
    {
        $this->remove('article', (int) $article->getKey());
    }

    public function removeArticleById(int $articleId): void
    {
        $this->remove('article', $articleId);
    }

    public function remove(string $sourceType, int|string $sourceId): void
    {
        KnowledgeDocument::query()->where('source_type', $sourceType)->where('source_id', (string) $sourceId)->delete();
    }

    private function store(
        string $sourceType,
        Model $record,
        string $locale,
        bool $eligible,
        string $title,
        ?string $url,
        array $contentParts,
        array $metadata,
        mixed $publishedAt,
        mixed $expiresAt = null,
    ): ?KnowledgeDocument {
        $sourceId = (string) $record->getKey();
        $identity = ['source_type' => $sourceType, 'source_id' => $sourceId, 'locale' => $locale];

        if (! $eligible) {
            $this->remove($sourceType, $sourceId);

            return null;
        }

        $content = $this->plainText(implode("\n\n", array_filter($contentParts, fn ($part): bool => filled($part))));
        $hash = hash('sha256', $content);
        $existing = KnowledgeDocument::query()->where($identity)->first();

        if ($existing?->content_hash === $hash
            && $existing->chunks()->where('embedding_model', $this->embeddings->model())
                ->where('embedding_dimensions', $this->embeddings->dimensions())->exists()) {
            $existing->update([
                'title' => $title,
                'canonical_url' => $url,
                'metadata' => $metadata,
                'visibility' => KnowledgeVisibility::PUBLIC,
                'index_version' => 1,
                'published_at' => $publishedAt,
                'expires_at' => $expiresAt,
                'indexed_at' => now(),
            ]);

            return $existing->refresh();
        }

        $chunks = $this->chunker->chunk($content);
        $vectors = $chunks === [] ? [] : $this->embeddings->embed($chunks);

        return DB::transaction(function () use ($identity, $sourceType, $sourceId, $title, $url, $content, $metadata, $hash, $publishedAt, $expiresAt, $chunks, $vectors): KnowledgeDocument {
            KnowledgeDocument::query()->where('source_type', $sourceType)->where('source_id', $sourceId)
                ->where('locale', '!=', $identity['locale'])->delete();

            $document = KnowledgeDocument::query()->updateOrCreate($identity, [
                'title' => $title,
                'canonical_url' => $url,
                'content' => $content,
                'metadata' => $metadata,
                'content_hash' => $hash,
                'index_version' => 1,
                'visibility' => KnowledgeVisibility::PUBLIC,
                'published_at' => $publishedAt,
                'expires_at' => $expiresAt,
                'indexed_at' => now(),
            ]);

            $document->chunks()->delete();
            foreach ($chunks as $position => $chunk) {
                $createdChunk = $document->chunks()->create([
                    'position' => $position,
                    'content' => $chunk,
                    'content_hash' => hash('sha256', $chunk),
                    'token_count' => max(1, (int) ceil(str_word_count($chunk) * 1.3)),
                    'character_count' => mb_strlen($chunk),
                    'embedding' => $vectors[$position],
                    'embedding_provider' => class_basename($this->embeddings),
                    'embedding_model' => $this->embeddings->model(),
                    'embedding_dimensions' => $this->embeddings->dimensions(),
                    'embedded_at' => now(),
                ]);

                if (DB::getDriverName() === 'pgsql') {
                    DB::update('UPDATE knowledge_chunks SET embedding_vector = ?::vector WHERE id = ?', [
                        $this->vectorLiteral($vectors[$position]), $createdChunk->id,
                    ]);
                }
            }

            return $document->refresh();
        });
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** @param list<float> $vector */
    private function vectorLiteral(array $vector): string
    {
        return '['.implode(',', array_map(fn ($value): string => (string) (float) $value, $vector)).']';
    }
}
