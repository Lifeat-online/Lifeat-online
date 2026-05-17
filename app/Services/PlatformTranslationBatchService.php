<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Classified;
use App\Models\Event;
use App\Models\Listing;
use App\Models\LocationNode;
use App\Models\Tag;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PlatformTranslationBatchService
{
    public function __construct(
        private readonly OpenRouterTranslationService $translator,
        private readonly PlatformInterfaceTranslationService $interfaceTranslations
    )
    {
    }

    public function sections(): array
    {
        return [
            'platform' => [
                'label' => 'Platform Interface',
                'type' => 'interface',
            ],
            'articles' => [
                'label' => 'Articles',
                'model' => Article::class,
                'query' => fn (): Builder => Article::query()->published(),
            ],
            'listings' => [
                'label' => 'Business Listings',
                'model' => Listing::class,
                'query' => fn (): Builder => Listing::query()->where('status', 'published'),
            ],
            'events' => [
                'label' => 'Events',
                'model' => Event::class,
                'query' => fn (): Builder => Event::query()->where('status', 'published'),
            ],
            'classifieds' => [
                'label' => 'Classifieds',
                'model' => Classified::class,
                'query' => fn (): Builder => Classified::query()->where('status', Classified::STATUS_PUBLISHED),
            ],
            'vouchers' => [
                'label' => 'Vouchers',
                'model' => Voucher::class,
                'query' => fn (): Builder => Voucher::query()->where('status', 'published'),
            ],
            'categories' => [
                'label' => 'Categories',
                'model' => Category::class,
                'query' => fn (): Builder => Category::query(),
            ],
            'tags' => [
                'label' => 'Tags',
                'model' => Tag::class,
                'query' => fn (): Builder => Tag::query(),
            ],
            'locations' => [
                'label' => 'Locations',
                'model' => LocationNode::class,
                'query' => fn (): Builder => LocationNode::query(),
            ],
        ];
    }

    public function sectionOptions(): array
    {
        return collect($this->sections())
            ->map(fn (array $section, string $key): array => [
                'key' => $key,
                'label' => $section['label'],
            ])
            ->values()
            ->all();
    }

    public function status(): array
    {
        return collect($this->sections())
            ->map(fn (array $section, string $key): array => [
                'key' => $key,
                'label' => $section['label'],
                'total' => ($section['type'] ?? null) === 'interface' ? $this->interfaceTranslations->status()['total'] : $this->queryFor($key)->count(),
                'missing' => ($section['type'] ?? null) === 'interface' ? $this->interfaceTranslations->status()['missing'] : $this->missingModelsForSection($key)->count(),
            ])
            ->values()
            ->all();
    }

    public function translate(array|string $sections, int $limit = 20, bool $force = false): array
    {
        $requested = $sections === 'all' ? array_keys($this->sections()) : (array) $sections;
        $limit = max(1, min($limit, 100));

        $summary = [
            'ok' => true,
            'processed' => 0,
            'translated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'sections' => [],
        ];

        foreach ($requested as $sectionKey) {
            if (! array_key_exists($sectionKey, $this->sections())) {
                continue;
            }

            if (($this->sections()[$sectionKey]['type'] ?? null) === 'interface') {
                $sectionSummary = $this->interfaceTranslations->translate($limit, $force);
                $summary['processed'] += $sectionSummary['processed'];
                $summary['translated'] += $sectionSummary['translated'];
                $summary['skipped'] += $sectionSummary['skipped'];
                $summary['failed'] += $sectionSummary['failed'];
                $summary['errors'] = $this->mergeErrors($summary['errors'], $sectionSummary['errors'] ?? []);
                $summary['sections'][] = $sectionSummary;
                continue;
            }

            $sectionSummary = [
                'key' => $sectionKey,
                'label' => $this->sections()[$sectionKey]['label'],
                'processed' => 0,
                'translated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => [],
            ];

            $targets = $force
                ? $this->queryFor($sectionKey)->limit($limit)->get()
                : $this->missingModelsForSection($sectionKey)->take($limit);

            foreach ($targets as $target) {
                foreach ($this->targetLocalesFor($target) as $locale) {
                    $sectionSummary['processed']++;
                    $summary['processed']++;

                    $result = $this->translator->translateModel($target, $locale, $force);

                    if (($result['ok'] ?? false) && ($result['message'] ?? '') === 'Translation is already current.') {
                        $sectionSummary['skipped']++;
                        $summary['skipped']++;
                    } elseif ($result['ok'] ?? false) {
                        $sectionSummary['translated']++;
                        $summary['translated']++;
                    } else {
                        $sectionSummary['failed']++;
                        $summary['failed']++;
                        $this->recordError(
                            $sectionSummary,
                            $summary,
                            $result['message'] ?? 'Translation failed.'
                        );
                    }
                }
            }

            $summary['sections'][] = $sectionSummary;
        }

        $summary['ok'] = $summary['failed'] === 0;

        return $summary;
    }

    private function recordError(array &$sectionSummary, array &$summary, string $message): void
    {
        $message = trim($message);

        if ($message === '') {
            return;
        }

        if (! in_array($message, $sectionSummary['errors'], true) && count($sectionSummary['errors']) < 5) {
            $sectionSummary['errors'][] = $message;
        }

        $summary['errors'] = $this->mergeErrors($summary['errors'], [$message]);
    }

    private function mergeErrors(array $current, array $incoming): array
    {
        foreach ($incoming as $message) {
            $message = trim((string) $message);

            if ($message !== '' && ! in_array($message, $current, true)) {
                $current[] = $message;
            }

            if (count($current) >= 5) {
                break;
            }
        }

        return $current;
    }

    private function queryFor(string $sectionKey): Builder
    {
        $sections = $this->sections();

        /** @var callable $factory */
        $factory = $sections[$sectionKey]['query'];

        return $factory();
    }

    private function missingModelsForSection(string $sectionKey): Collection
    {
        return $this->queryFor($sectionKey)
            ->with('contentTranslations')
            ->get()
            ->filter(function (Model $model): bool {
                $translated = $model->contentTranslations->pluck('locale');

                return $this->targetLocalesFor($model)
                    ->diff($translated)
                    ->isNotEmpty();
            })
            ->values();
    }

    private function targetLocalesFor(Model $model): Collection
    {
        return $this->targetLocales()
            ->reject(fn (string $locale): bool => method_exists($model, 'sourceLocale') && $locale === $model->sourceLocale())
            ->values();
    }

    private function targetLocales(): Collection
    {
        return collect(config('localization.supported'))
            ->keys()
            ->values();
    }
}
