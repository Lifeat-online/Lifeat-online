<?php

namespace App\Services;

use App\Models\AiGeneration;
use Illuminate\Support\Str;

class AiCostEstimatorService
{
    public function currency(): string
    {
        return Str::upper((string) config('ai_costs.currency', 'ZAR'));
    }

    public function estimateText(string $provider, string $model, int $inputTokens, int $outputTokens): float
    {
        $rate = $this->rateForText($provider, $model);

        $input = max(0, $inputTokens) * ((float) ($rate['input_per_million'] ?? 0)) / 1_000_000;
        $output = max(0, $outputTokens) * ((float) ($rate['output_per_million'] ?? 0)) / 1_000_000;

        return $this->convertUsd($input + $output);
    }

    public function estimateImage(string $provider, string $model, int $images = 1): float
    {
        $rate = $this->rateForImage($provider, $model);

        return $this->convertUsd(max(0, $images) * $rate);
    }

    public function estimateVoice(string $provider, string $model, int $characters): float
    {
        $rate = $this->rateForVoice($provider, $model);

        return $this->convertUsd(max(0, $characters) * $rate / 1000);
    }

    public function estimateForGeneration(AiGeneration $generation): float
    {
        if ($generation->feature_key === 'article_image') {
            return $this->estimateImage((string) $generation->provider, (string) $generation->model);
        }

        if ($generation->feature_key === 'ask_life_voice') {
            $characters = (int) data_get($generation->output_payload, 'character_count', $generation->token_input_estimate ?: 0);

            return $this->estimateVoice((string) $generation->provider, (string) $generation->model, $characters);
        }

        return $this->estimateText(
            (string) $generation->provider,
            (string) $generation->model,
            (int) $generation->token_input_estimate,
            (int) $generation->token_output_estimate,
        );
    }

    public function format(float|int|string|null $cost): string
    {
        if ($cost === null || $cost === '') {
            return '-';
        }

        $value = (float) $cost;

        if ($value <= 0.0) {
            return $this->currencyLabel().' 0.000000';
        }

        return $this->currencyLabel().' '.number_format($value, 6);
    }

    public function exchangeRate(): float
    {
        return max(0, (float) config('ai_costs.usd_to_zar', 16.46));
    }

    private function currencyLabel(): string
    {
        return $this->currency() === 'ZAR' ? 'R' : $this->currency();
    }

    private function rateForText(string $provider, string $model): array
    {
        $config = (array) config('ai_costs.text.'.Str::lower($provider), []);

        foreach ((array) ($config['contains'] ?? []) as $needle => $rate) {
            if ($needle !== '' && str_contains(Str::lower($model), Str::lower((string) $needle))) {
                return (array) $rate;
            }
        }

        foreach ((array) ($config['models'] ?? []) as $modelKey => $rate) {
            if (Str::lower($modelKey) === Str::lower($model)) {
                return (array) $rate;
            }
        }

        return (array) ($config['default'] ?? ['input_per_million' => 0, 'output_per_million' => 0]);
    }

    private function rateForImage(string $provider, string $model): float
    {
        $config = (array) config('ai_costs.image.'.Str::lower($provider), []);

        foreach ((array) ($config['contains'] ?? []) as $needle => $rate) {
            if ($needle !== '' && str_contains(Str::lower($model), Str::lower((string) $needle))) {
                return (float) $rate;
            }
        }

        foreach ((array) ($config['models'] ?? []) as $modelKey => $rate) {
            if (Str::lower($modelKey) === Str::lower($model)) {
                return (float) $rate;
            }
        }

        return (float) ($config['default_per_image'] ?? 0);
    }

    private function rateForVoice(string $provider, string $model): float
    {
        $config = (array) config('ai_costs.voice.'.Str::lower($provider), []);

        foreach ((array) ($config['models'] ?? []) as $modelKey => $rate) {
            if (Str::lower($modelKey) === Str::lower($model)) {
                return (float) $rate;
            }
        }

        return (float) ($config['default_per_1000_characters'] ?? 0);
    }

    private function round(float $value): float
    {
        return round(max(0, $value), 6);
    }

    private function convertUsd(float $usd): float
    {
        if ($this->currency() !== 'ZAR') {
            return $this->round($usd);
        }

        return $this->round($usd * $this->exchangeRate());
    }
}
