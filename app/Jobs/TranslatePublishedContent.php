<?php

namespace App\Jobs;

use App\Services\OpenRouterTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranslatePublishedContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $translatableType,
        public readonly int|string $translatableId,
        public readonly bool $force = false,
    ) {
    }

    public function handle(OpenRouterTranslationService $translator): void
    {
        if (! $translator->configured() || ! class_exists($this->translatableType)) {
            return;
        }

        $class = $this->translatableType;

        if (! is_subclass_of($class, Model::class)) {
            return;
        }

        $model = $class::query()->find($this->translatableId);

        if (! $model || ! method_exists($model, 'translatableContent') || ! method_exists($model, 'contentTranslations')) {
            return;
        }

        $sourceLocale = method_exists($model, 'sourceLocale')
            ? $model->sourceLocale()
            : (string) config('localization.default', 'en');

        foreach (array_keys((array) config('localization.supported', [])) as $targetLocale) {
            if ($targetLocale === $sourceLocale) {
                continue;
            }

            $translator->translateModel($model, $targetLocale, $this->force);
        }
    }
}
